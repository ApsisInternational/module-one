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

    const EVENT_TYPE_DONE_PATH_MAPPING = [
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_QUOTE_HISTORY_DONE_FLAG,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_ORDER_HISTORY_DONE_FLAG,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_REVIEW_HISTORY_DONE_FLAG,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_WISHLIST_HISTORY_DONE_FLAG
    ];

    const EVENT_TYPE_HISTORY_DURATION_PATH_MAPPING = [
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_EVENTS_DURATION,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_EVENTS_DURATION,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_EVENTS_DURATION,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_EVENTS_DURATION
    ];

    const EVENT_TYPE_HISTORY_DURATION_TIMESTAMP_PATH_MAPPING = [
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_DURATION_TIMESTAMP,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_DURATION_TIMESTAMP,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_DURATION_TIMESTAMP,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_DURATION_TIMESTAMP
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
        $clearCache = false;
        foreach ($stores as $store) {
            try {
                if (! $store->getWebsite()) {
                    continue;
                }
                $account = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
                $types = $this->getEventTypesToFetchHistoryFor($apsisCoreHelper, $store);
                if ($account && ! empty($types)) {
                    $profileCollection = $this->profileCollectionFactory->create()
                        ->getProfileCollectionForStore($store->getWebsite()->getStoreIds());
                    if ($profileCollection->getSize()) {
                        $this->runByType($types, $store, $apsisCoreHelper, $profileCollection);
                        $clearCache = true;
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
                continue;
            }
        }
        if ($clearCache) {
            $apsisCoreHelper->cleanCache();
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     *
     * @return array
     */
    private function getEventTypesToFetchHistoryFor(ApsisCoreHelper $apsisCoreHelper, StoreInterface $store)
    {
        $eventTypeToSync = [];
        foreach (self::EVENT_TYPE_DONE_PATH_MAPPING as $type => $path) {
            if (! $this->isHistoricalEventDataAlreadySyncedForEventType($apsisCoreHelper, $store, $path)) {
                $eventTypeToSync[] = $type;
            }
        }
        return $eventTypeToSync;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     * @param string $path
     *
     * @return bool
     */
    private function isHistoricalEventDataAlreadySyncedForEventType(
        ApsisCoreHelper $apsisCoreHelper,
        StoreInterface $store,
        string $path
    ) {
        return (boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            $path
        );
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param int $eventType
     *
     * @return array
     */
    private function calculatePeriod(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper, int $eventType)
    {
        $period = [];
        try {
            $pastEventsDuration = (int) $apsisCoreHelper->getStoreConfig(
                $store,
                self::EVENT_TYPE_HISTORY_DURATION_PATH_MAPPING[$eventType]
            );
            if ($pastEventsDuration) {
                $to = $this->getToDatestamp($store, $apsisCoreHelper, $eventType);
                $from = $this->getFromDatestamp($pastEventsDuration, $to, $apsisCoreHelper);
                if (strlen($to) && strlen($from)) {
                    $period = [
                        'from' => $from,
                        'to' => $to,
                        'date' => true,
                    ];
                }
            }
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $period;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param int $eventType
     *
     * @return string
     */
    private function getToDatestamp(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper, int $eventType)
    {
        try {
            $timestamp = $this->eventCollectionFactory->create()
                ->getTimestampFromFirstEventEntryByStore($store->getId(), $eventType);
            return strlen($timestamp) ? $timestamp :
                (string) $apsisCoreHelper->getStoreConfig(
                    $store,
                    self::EVENT_TYPE_HISTORY_DURATION_TIMESTAMP_PATH_MAPPING[$eventType]
                );
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return '';
        }
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
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return '';
        }
    }

    /**
     * @param array $types
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollection $profileCollection
     */
    private function runByType(
        array $types,
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection
    ) {
        foreach ($types as $type) {
            try {
                if (! empty($period = $this->calculatePeriod($store, $apsisCoreHelper, $type))) {
                    switch ($type) {
                        case Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST:
                            $this->historicalWishlist->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period
                            );
                            break;
                        case Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW:
                            $this->historicalReviews->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period
                            );
                            break;
                        case Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART:
                            $this->historicalCarts->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period
                            );
                            break;
                        case Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER:
                            $this->historicalOrders->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period
                            );
                            break;
                        default:
                            $apsisCoreHelper->log(__METHOD__, 'Unsupported type.');
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                continue;
            }
        }
    }
}
