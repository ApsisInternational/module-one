<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\Data\Wishlist\WishlistData;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\Item as MagentoWishlistItem;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistItemCollectionFactory;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory as WishlistCollectionFactory;
use Throwable;

class WishlistEvents extends AbstractEvents
{
    /**
     * @var WishlistCollectionFactory
     */
    private WishlistCollectionFactory $wishlistCollectionFactory;

    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param WishlistItemCollectionFactory $collectionFactory
     * @param WishlistData $eventData
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        WishlistItemCollectionFactory $collectionFactory,
        WishlistData $eventData,
        WishlistCollectionFactory $wishlistCollectionFactory
    ) {
        $this->wishlistCollectionFactory = $wishlistCollectionFactory;
        parent::__construct($dateTime, $eventResource, $collectionFactory, $eventData);
    }

    /**
     * @inheirtDoc
     */
    public function process(StoreInterface $store, BaseService $baseService, array $profileColArray): void
    {
        $eventsToRegister = $this->findAndRegister($store, $baseService, $profileColArray);
        $this->registerEvents($eventsToRegister, $baseService, $store->getId(), 'Product Wished');
    }

    /**
     * @inheirtDoc
     */
    protected function findAndRegister(
        StoreInterface $store,
        BaseService $baseService,
        array $profileColArray
    ): array {
        try {
            if (empty($profileColArray)) {
                return [];
            }

            $wishlistArrayCollection = $this->getWishlistCollection(
                array_keys($profileColArray),
                $baseService
            );
            if (empty($wishlistArrayCollection)) {
                return [];
            }

            return $this->getEventsToRegister(
                $baseService,
                $wishlistArrayCollection,
                $profileColArray,
                $store
            );
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @inheirtDoc
     */
    protected function getEventsToRegister(
        BaseService $baseService,
        array $entityCollectionArr,
        array $profileCollectionArray,
        StoreInterface $store
    ): array {
        $eventsToRegister = [];
        $wishlistItemCollection = $this->getFormattedArrFromCollection(
            array_keys($entityCollectionArr),
            $store,
            $baseService
        );
        if (empty($wishlistItemCollection)) {
            return $eventsToRegister;
        }

        /** @var  MagentoWishlistItem $wishlistItem */
        foreach ($wishlistItemCollection as $wishlistItem) {
            try {
                if (! isset($entityCollectionArr[$wishlistItem->getWishlistId()]) ||
                    empty($wishList = $entityCollectionArr[$wishlistItem->getWishlistId()]) ||
                    ! isset($profileCollectionArray[$wishList->getCustomerId()]) ||
                    empty($profile = $profileCollectionArray[$wishList->getCustomerId()]) ||
                    empty($product = $wishlistItem->getProduct())
                ) {
                    continue;
                }

                $eventData = $this->eventData->getWishedData(
                    $profile,
                    $store->getId(),
                    $product,
                    $baseService
                );

                if (! empty($eventData)) {
                    $eventDataForEvent = $this->getFormattedEventDataForRecord(
                        $wishlistItem->getStoreId(),
                        $profile,
                        EventModel::EVENT_PRODUCT_WISHED,
                        $wishlistItem->getAddedAt(),
                        json_encode($eventData),
                        $baseService
                    );

                    if (! empty($eventDataForEvent)) {
                        $eventsToRegister[] = $eventDataForEvent;
                    }
                }
            } catch (Throwable $e) {
                $baseService->logError(__METHOD__, $e);
                continue;
            }
        }

        return $eventsToRegister;
    }

    /**
     * @param array $customerIds
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getWishlistCollection(array $customerIds, BaseService $baseService): array
    {
        $collectionArray = [];

        try {
            foreach (array_chunk($customerIds, self::QUERY_LIMIT) as $customerIdsChunk) {
                $collection = $this->wishlistCollectionFactory
                    ->create()
                    ->filterByCustomerIds($customerIdsChunk);

                if ($collection->getSize()) {
                    foreach ($collection as $item) {
                        $collectionArray[$item->getId()] =  $item;
                    }
                }
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $collectionArray;
    }
}
