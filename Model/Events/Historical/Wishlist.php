<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\Event;
use Apsis\One\Model\Events\Historical\Wishlist\Data as WishlistEventData;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\ResourceModel\Item\Collection as WishlistItemCollection;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistItemCollectionFactory;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory as WishlistCollectionFactory;
use Magento\Wishlist\Model\Wishlist as MagentoWishlist;
use Magento\Wishlist\Model\Item as MagentoWishlistItem;
use Magento\Store\Model\App\EmulationFactory;

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
     * @var EmulationFactory
     */
    private $emulationFactory;

    /**
     * Wishlist constructor.
     *
     * @param DateTime $dateTime
     * @param WishlistItemCollectionFactory $wishlistItemCollectionFactory
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     * @param WishlistEventData $wishlistEventData
     * @param EmulationFactory $emulationFactory
     * @param EventResource $eventResource
     */
    public function __construct(
        DateTime $dateTime,
        WishlistItemCollectionFactory $wishlistItemCollectionFactory,
        WishlistCollectionFactory $wishlistCollectionFactory,
        WishlistEventData $wishlistEventData,
        EmulationFactory $emulationFactory,
        EventResource $eventResource
    ) {
        $this->dateTime = $dateTime;
        $this->eventResource = $eventResource;
        $this->emulationFactory = $emulationFactory;
        $this->eventData = $wishlistEventData;
        $this->wishlistCollectionFactory = $wishlistCollectionFactory;
        $this->wishlistItemCollectionFactory = $wishlistItemCollectionFactory;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollection $profileCollection
     * @param array $duration
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $duration
    ) {
        if ((boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_WISHLIST
        )) {
            $appEmulation = $this->emulationFactory->create();
            try {
                $appEmulation->startEnvironmentEmulation($store->getId(), Area::AREA_FRONTEND, true);
                if (! empty($profileCollectionArray =
                        $this->getFormattedProfileCollection($profileCollection, $apsisCoreHelper)) &&
                    ! empty($wishlistArrayCollection = $this->getWishlistCollection(
                        array_keys($profileCollectionArray),
                        $apsisCoreHelper
                    )) &&
                    ! empty($wishlistItemCollection = $this->getWishlistItemCollection(
                        $store->getId(),
                        array_keys($wishlistArrayCollection),
                        $duration,
                        $apsisCoreHelper
                    ))
                ) {
                    $eventsToRegister = $this->getEventsToRegister(
                        $wishlistItemCollection,
                        $wishlistArrayCollection,
                        $profileCollectionArray,
                        $apsisCoreHelper,
                        $store
                    );
                    $this->registerEvents(
                        $eventsToRegister,
                        $apsisCoreHelper,
                        $store,
                        ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_WISHLIST_HISTORY_DONE_FLAG
                    );
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                $appEmulation->stopEnvironmentEmulation();
            }
            $appEmulation->stopEnvironmentEmulation();
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
            $wishlistCollection = $this->wishlistCollectionFactory->create()
                ->filterByCustomerIds($customerIds);
            if ($wishlistCollection->getSize()) {
                /** @var MagentoWishlist $wishlist */
                foreach ($wishlistCollection as $wishlist) {
                    $wishlistCollectionArray[$wishlist->getId()] =  $wishlist;
                }
            }
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
                if (isset($wishlistArrayCollection[$wishlistItem->getWishlistId()]) &&
                    isset($profileCollectionArray[$wishlistArrayCollection[$wishlistItem->getWishlistId()]
                            ->getCustomerId()])
                ) {
                    $eventData = $this->eventData->getDataArr(
                        $wishlistArrayCollection[$wishlistItem->getWishlistId()],
                        $store,
                        $wishlistItem,
                        $wishlistItem->getProduct(),
                        $apsisCoreHelper
                    );
                    if (! empty($eventData)) {
                        $eventDataForEvent = $this->getEventData(
                            $wishlistItem->getStoreId(),
                            $profileCollectionArray[$wishlistArrayCollection[$wishlistItem->getWishlistId()]
                                ->getCustomerId()],
                            Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
                            $wishlistItem->getAddedAt(),
                            $apsisCoreHelper->serialize($eventData),
                            $apsisCoreHelper
                        );
                        if (! empty($eventDataForEvent)) {
                            $eventsToRegister[] = $eventDataForEvent;
                        }
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                continue;
            }
        }
        return $eventsToRegister;
    }
}
