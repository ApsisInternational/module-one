<?php

namespace Apsis\One\Model\Events;

use Apsis\One\Model\Event;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Sync\SyncInterface;
use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Apsis\One\Model\Events\Historical\Carts;
use Apsis\One\Model\Events\Historical\Orders;
use Apsis\One\Model\Events\Historical\Reviews;
use Apsis\One\Model\Events\Historical\Wishlist;
use Apsis\One\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;

class Historical implements SyncInterface
{
    /**
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * @var EventCollectionFactory
     */
    private $eventCollectionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var Carts
     */
    private $historicalCarts;

    /**
     * @var Orders
     */
    private $historicalOrders;

    /**
     * @var Reviews
     */
    private $historicalReviews;

    /**
     * @var Wishlist
     */
    private $historicalWishlist;

    /**
     * @var array
     */
    private $eventTypesForHistory = [
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST
    ];

    /**
     * Historical constructor.
     *
     * @param Carts $historicalCarts
     * @param Orders $historicalOrders
     * @param Reviews $historicalReviews
     * @param Wishlist $historicalWishlist
     * @param EventCollectionFactory $eventCollectionFactory
     * @param ApsisDateHelper $apsisDateHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     */
    public function __construct(
        Carts $historicalCarts,
        Orders $historicalOrders,
        Reviews $historicalReviews,
        Wishlist $historicalWishlist,
        EventCollectionFactory $eventCollectionFactory,
        ApsisDateHelper $apsisDateHelper,
        ProfileCollectionFactory $profileCollectionFactory
    ) {
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->historicalWishlist = $historicalWishlist;
        $this->historicalCarts = $historicalCarts;
        $this->historicalOrders = $historicalOrders;
        $this->historicalReviews = $historicalReviews;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function process(ApsisCoreHelper $apsisCoreHelper)
    {
        $stores = $apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            try {
                $account = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
                $period = $this->calculatePeriod($store, $apsisCoreHelper);
                if ($account && ! $this->isHistoricalEventDataAlreadySynced($apsisCoreHelper, $store) &&
                    ! empty($period)
                ) {
                    $profileCollection = $this->profileCollectionFactory->create()
                        ->getCustomerProfileCollectionForStore($store->getId());
                    if ($profileCollection->getSize() &&
                        ! empty($formattedProfileCollectionArray =
                            $this->getFormattedProfileCollection($profileCollection))
                    ) {
                        //$this->historicalWishlist
                        //    ->fetchForStore($store, $apsisCoreHelper, $formattedProfileCollectionArray, $period);
                        //$this->historicalReviews
                        //    ->fetchForStore($store, $apsisCoreHelper, $formattedProfileCollectionArray, $period);
                        //$this->historicalCarts
                        //    ->fetchForStore($store, $apsisCoreHelper, $formattedProfileCollectionArray, $period);
                        //$this->historicalOrders
                        //    ->fetchForStore($store, $apsisCoreHelper, $formattedProfileCollectionArray, $period);
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            }
        }
    }

    /**
     * @param ProfileCollection $profileCollection
     *
     * @return array
     */
    private function getFormattedProfileCollection(ProfileCollection $profileCollection)
    {
        $formattedProfileCollectionArray = [];
        foreach ($profileCollection as $profile) {
            $formattedProfileCollectionArray[$profile->getCustomerId()] = $profile;
        }
        return $formattedProfileCollectionArray;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isHistoricalEventDataAlreadySynced(ApsisCoreHelper $apsisCoreHelper, StoreInterface $store)
    {
        return (boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DONE_FLAG
        );
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    private function calculatePeriod(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper)
    {
        $period = [];
        $pastEventsDuration = (int) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DURATION
        );
        if ($pastEventsDuration) {
            $to = $this->getToDatestamp($store, $apsisCoreHelper);
            $from = $this->getFromDatestamp($pastEventsDuration, $to, $apsisCoreHelper);
            if (strlen($to) && strlen($from)) {
                $period = [
                    'from' => $from,
                    'to' => $to,
                    'date' => true,
                ];
            }

        }
        return $period;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return string
     */
    private function getToDatestamp(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper)
    {
        $timestamp = $this->eventCollectionFactory->create()
            ->getTimestampFromFirstEventEntryByStore($store->getId(), $this->eventTypesForHistory);
        return strlen($timestamp) ? $timestamp :
            (string) $apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DURATION_TIMESTAMP
            );
    }

    /**
     * @param int $pastEventsDuration
     * @param string $to
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return string
     */
    private function getFromDatestamp(int $pastEventsDuration, string $to, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            return strlen($to) ? $this->apsisDateHelper
                ->getDateTimeFromTime($to)
                ->sub($this->apsisDateHelper->getDateIntervalFromIntervalSpec(sprintf('P%sM', $pastEventsDuration)))
                ->format('Y-m-d H:i:s') : '';
        } catch (Exception $e) {
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return '';
        }
    }
}
