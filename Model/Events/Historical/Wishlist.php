<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\Event;
use Apsis\One\Model\Events\Historical\Wishlist\Data as WishlistEventData;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Throwable;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\ResourceModel\Item\Collection as WishlistItemCollection;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistItemCollectionFactory;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory as WishlistCollectionFactory;
use Magento\Wishlist\Model\Item as MagentoWishlistItem;

class Wishlist extends HistoricalEvent implements EventHistoryInterface
{
    /**
     * @var WishlistItemCollectionFactory
     */
    private $wishlistItemCollectionFactory;

    /**
     * @var WishlistCollectionFactory
     */
    private $wishlistCollectionFactory;

    /**
     * Wishlist constructor.
     *
     * @param DateTime $dateTime
     * @param WishlistItemCollectionFactory $wishlistItemCollectionFactory
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     * @param WishlistEventData $wishlistEventData
     * @param EventResource $eventResource
     */
    public function __construct(
        DateTime $dateTime,
        WishlistItemCollectionFactory $wishlistItemCollectionFactory,
        WishlistCollectionFactory $wishlistCollectionFactory,
        WishlistEventData $wishlistEventData,
        EventResource $eventResource
    ) {
        $this->dateTime = $dateTime;
        $this->eventResource = $eventResource;
        $this->eventData = $wishlistEventData;
        $this->wishlistCollectionFactory = $wishlistCollectionFactory;
        $this->wishlistItemCollectionFactory = $wishlistItemCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $duration,
        array $profileCollectionArray
    ) {
        try {
            if (empty($profileCollectionArray)) {
                return;
            }

            $wishlistArrayCollection = $this->getWishlistCollection(
                array_keys($profileCollectionArray),
                $apsisCoreHelper
            );
            if (empty($wishlistArrayCollection)) {
                return;
            }

            $wishlistItemCollection = $this->getWishlistItemCollection(
                $store->getId(),
                array_keys($wishlistArrayCollection),
                $duration,
                $apsisCoreHelper
            );
            if (empty($wishlistItemCollection)) {
                return;
            }

            $eventsToRegister = $this->getEventsToRegister(
                $wishlistItemCollection,
                $wishlistArrayCollection,
                $profileCollectionArray,
                $apsisCoreHelper,
                $store
            );

            $status = $this->registerEvents($eventsToRegister, $apsisCoreHelper);

            if ($status) {
                $info = [
                    'Total Events Inserted' => $status,
                    'Store Id' => $store->getId()
                ];
                $apsisCoreHelper->debug(__METHOD__, $info);
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array $customerIds
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    private function getWishlistCollection(array $customerIds, ApsisCoreHelper $apsisCoreHelper)
    {
        $wishlistCollectionArray = [];

        try {
            $wishlistCollection = $this->wishlistCollectionFactory
                ->create()
                ->filterByCustomerIds($customerIds);

            if ($wishlistCollection->getSize()) {
                foreach ($wishlistCollection as $wishlist) {
                    $wishlistCollectionArray[$wishlist->getId()] =  $wishlist;
                }
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $wishlistCollectionArray;
    }

    /**
     * @param int $storeId
     * @param array $wishlistIds
     * @param array $period
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return WishlistItemCollection|array
     */
    private function getWishlistItemCollection(
        int $storeId,
        array $wishlistIds,
        array $period,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        try {
            $collection = $this->wishlistItemCollectionFactory->create()
                ->addStoreFilter([$storeId])
                ->addFieldToFilter('wishlist_id', ['in' => $wishlistIds])
                ->addFieldToFilter('added_at', $period)
                ->setVisibilityFilter()
                ->setSalableFilter();
            $collection->getSelect()->group('wishlist_item_id');
            return $collection;
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param WishlistItemCollection $wishlistItemCollection
     * @param array $wishlistArrayCollection
     * @param array $profileCollectionArray
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     *
     * @return array
     */
    private function getEventsToRegister(
        WishlistItemCollection $wishlistItemCollection,
        array $wishlistArrayCollection,
        array $profileCollectionArray,
        ApsisCoreHelper $apsisCoreHelper,
        StoreInterface $store
    ) {
        $eventsToRegister = [];

        /** @var  MagentoWishlistItem $wishlistItem */
        foreach ($wishlistItemCollection as $wishlistItem) {
            try {
                if (empty($wishList = $wishlistArrayCollection[$wishlistItem->getWishlistId()]) ||
                    empty($profile = $profileCollectionArray[$wishList->getCustomerId()]) ||
                    empty($product = $wishlistItem->getProduct())
                ) {
                    continue;
                }

                $eventData = $this->eventData->getDataArr(
                    $wishList,
                    $store,
                    $wishlistItem,
                    $product,
                    $apsisCoreHelper
                );

                if (! empty($eventData)) {
                    $eventDataForEvent = $this->getEventData(
                        $wishlistItem->getStoreId(),
                        $profile,
                        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
                        $wishlistItem->getAddedAt(),
                        $apsisCoreHelper->serialize($eventData),
                        $apsisCoreHelper
                    );

                    if (! empty($eventDataForEvent)) {
                        $eventsToRegister[] = $eventDataForEvent;
                    }
                }
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                continue;
            }
        }

        return $eventsToRegister;
    }
}
