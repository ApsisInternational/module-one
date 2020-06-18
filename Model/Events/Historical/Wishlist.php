<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\Event;
use Apsis\One\Model\Events\Historical\Wishlist\Data as WishlistEventData;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\App\Area;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\ResourceModel\Item\Collection as WishlistItemCollection;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistItemCollectionFactory;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory as WishlistCollectionFactory;
use \Magento\Wishlist\Model\Wishlist as MagentoWishlist;
use \Magento\Wishlist\Model\Item as MagentoWishlistItem;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Store\Model\App\Emulation;

class Wishlist extends HistoricalEvent implements EventHistoryInterface
{
    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var WishlistItemCollectionFactory
     */
    private $wishlistItemCollectionFactory;

    /**
     * @var WishlistCollectionFactory
     */
    private $wishlistCollectionFactory;

    /**
     * @var WishlistEventData
     */
    private $wishlistEventData;

    /**
     * @var EmulationFactory
     */
    private $emulationFactory;

    /**
     * Wishlist constructor.
     *
     * @param WishlistItemCollectionFactory $wishlistItemCollectionFactory
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     * @param WishlistEventData $wishlistEventData
     * @param EmulationFactory $emulationFactory
     * @param EventResource $eventResource
     */
    public function __construct(
        WishlistItemCollectionFactory $wishlistItemCollectionFactory,
        WishlistCollectionFactory $wishlistCollectionFactory,
        WishlistEventData $wishlistEventData,
        EmulationFactory $emulationFactory,
        EventResource $eventResource
    ) {
        $this->eventResource = $eventResource;
        $this->emulationFactory = $emulationFactory;
        $this->wishlistEventData = $wishlistEventData;
        $this->wishlistCollectionFactory = $wishlistCollectionFactory;
        $this->wishlistItemCollectionFactory = $wishlistItemCollectionFactory;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $profileCollectionArray
     * @param array $duration
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        array $profileCollectionArray,
        array $duration
    ) {
        if ((boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_WISHLIST
        )) {
            /** @var Emulation $appEmulation */
            $appEmulation = $this->emulationFactory->create();
            try {
                $appEmulation->startEnvironmentEmulation($store->getId(), Area::AREA_FRONTEND, true);
                if (! empty($wishlistArrayCollection =
                        $this->getWishlistCollection(array_keys($profileCollectionArray), $apsisCoreHelper)) &&
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
                    if (! empty($eventsToRegister)) {
                        $this->eventResource->insertEvents($eventsToRegister, $apsisCoreHelper);
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            return $this->wishlistItemCollectionFactory->create()
                ->addStoreFilter([$storeId])
                ->addFieldToFilter('wishlist_id', ['in' => $wishlistIds])
                ->addFieldToFilter('added_at', $period)
                ->setVisibilityFilter()
                ->setSalableFilter();
        } catch (Exception $e) {
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
                    $eventsToRegister[] = $this->getEventData(
                        $profileCollectionArray[$wishlistArrayCollection[$wishlistItem->getWishlistId()]
                            ->getCustomerId()],
                        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
                        $wishlistItem->getAddedAt(),
                        $apsisCoreHelper->serialize(
                            $this->wishlistEventData->getDataArr(
                                $wishlistArrayCollection[$wishlistItem->getWishlistId()],
                                $store,
                                $wishlistItem,
                                $wishlistItem->getProduct(),
                                $apsisCoreHelper
                            )
                        )
                    );
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                continue;
            }
        }
        return $eventsToRegister;
    }
}
