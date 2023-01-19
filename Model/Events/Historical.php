<?php

namespace Apsis\One\Model\Events;

use Apsis\One\Model\Event;
use Apsis\One\Model\Events\Historical\Carts;
use Apsis\One\Model\Events\Historical\Orders;
use Apsis\One\Model\Events\Historical\Reviews;
use Apsis\One\Model\Events\Historical\Wishlist;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Sync\SyncInterface;
use Apsis\One\Setup\UpgradeData;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Historical implements SyncInterface
{
    const FETCH_HISTORICAL_EVENTS = [
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
    ];

    /**
     * @var ApsisDateHelper
     */
    private ApsisDateHelper $apsisDateHelper;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var Carts
     */
    private Carts $historicalCarts;

    /**
     * @var Orders
     */
    private Orders $historicalOrders;

    /**
     * @var Reviews
     */
    private Reviews $historicalReviews;

    /**
     * @var Wishlist
     */
    private Wishlist $historicalWishlist;

    /**
     * @var EmulationFactory
     */
    private EmulationFactory $emulationFactory;

    /**
     * Historical constructor.
     *
     * @param Carts $historicalCarts
     * @param Orders $historicalOrders
     * @param Reviews $historicalReviews
     * @param Wishlist $historicalWishlist
     * @param ApsisDateHelper $apsisDateHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EmulationFactory $emulationFactory
     */
    public function __construct(
        Carts $historicalCarts,
        Orders $historicalOrders,
        Reviews $historicalReviews,
        Wishlist $historicalWishlist,
        ApsisDateHelper $apsisDateHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        EmulationFactory $emulationFactory
    ) {
        $this->emulationFactory = $emulationFactory;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->historicalWishlist = $historicalWishlist;
        $this->historicalCarts = $historicalCarts;
        $this->historicalOrders = $historicalOrders;
        $this->historicalReviews = $historicalReviews;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function process(ApsisCoreHelper $apsisCoreHelper): void
    {
        $period = $this->calculatePeriod($apsisCoreHelper);
        if (empty($period)) {
            $info = ['Message' => 'Period is empty for fetching historical events. See error'];
            $apsisCoreHelper->debug(__METHOD__, $info);
            return;
        } else {
            $info = ['Message' => 'Searching for historical event with period.'];
            $apsisCoreHelper->debug(__METHOD__, array_merge($info, $period));
        }

        foreach ($apsisCoreHelper->getStores() as $store) {
            $emulate = $this->emulationFactory->create();
            try {
                $emulate->startEnvironmentEmulation($store->getId(), Area::AREA_FRONTEND, true);
                $profileCollection = $this->profileCollectionFactory
                    ->create()
                    ->getProfileCollectionForStore($store->getId());

                if ($profileCollection->getSize()) {
                    $this->runByType($store, $apsisCoreHelper, $profileCollection, $period);
                }
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
                $emulate->stopEnvironmentEmulation();
                continue;
            }
            $emulate->stopEnvironmentEmulation();
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    private function calculatePeriod(ApsisCoreHelper $apsisCoreHelper): array
    {
        try {
            $to = $this->apsisDateHelper->getDateTimeFromTime();
            $from = $this->apsisDateHelper->getDateTimeFromTime()
                ->sub($this->apsisDateHelper->getDateIntervalFromIntervalSpec('P24M'));

            return [
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
                'date' => true
            ];
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollection $profileCollection
     * @param array $period
     *
     * @return void
     */
    private function runByType(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $period
    ): void {
        foreach (self::FETCH_HISTORICAL_EVENTS as $type) {
            try {
                $profileCollectionArray = $this
                    ->getFormattedProfileCollection($profileCollection, $apsisCoreHelper, $store);

                switch ($type) {
                    case Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST:
                        if (! $this->isAlreadyDoneForStore($apsisCoreHelper, $store, $type)) {
                            $this->historicalWishlist->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period,
                                $profileCollectionArray
                            );
                        }
                        break;
                    case Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW:
                        if (! $this->isAlreadyDoneForStore($apsisCoreHelper, $store, $type)) {
                            $this->historicalReviews->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period,
                                $profileCollectionArray
                            );
                        }
                        break;
                    case Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART:
                        if (! $this->isAlreadyDoneForStore($apsisCoreHelper, $store, $type)) {
                            $this->historicalCarts->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period,
                                $profileCollectionArray
                            );
                        }
                        break;
                    case Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER:
                        if (! $this->isAlreadyDoneForStore($apsisCoreHelper, $store, $type)) {
                            $this->historicalOrders->fetchForStore(
                                $store,
                                $apsisCoreHelper,
                                $profileCollection,
                                $period,
                                $this->getFormattedProfileCollection($profileCollection, $apsisCoreHelper, $store, true)
                            );
                        }
                        break;
                    default:
                        $apsisCoreHelper->log(__METHOD__, ['Unsupported type.']);
                }
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                continue;
            }
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     * @param int $type
     *
     * @return bool
     */
    private function isAlreadyDoneForStore(ApsisCoreHelper $apsisCoreHelper, StoreInterface $store, int $type): bool
    {
        try {
            $contexts = [
                ScopeInterface::SCOPE_STORES => $store->getId(),
                ScopeInterface::SCOPE_WEBSITES => $store->getWebsiteId(),
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT => 0
            ];

            foreach ($contexts as $scope => $id) {
                $done = (boolean) $apsisCoreHelper->getDataCollectionByContextAndPath(
                    $scope,
                    $id,
                    UpgradeData::PRE_220_HISTORICAL_EVENT_DONE_CONFIGS[$type]
                )->getSize();
                if ($done) {
                    break;
                }
            }

            if ($done) {
                $info = [
                    'Message' => 'Skipping. In previous version already fetched events using crontab.',
                    'Event type' => $type,
                    'Pre 2.2.0 config path' => UpgradeData::PRE_220_HISTORICAL_EVENT_DONE_CONFIGS[$type],
                    'Store Id' => $store->getId()
                ];
                $apsisCoreHelper->debug(__METHOD__, $info);
            }

            return $done;
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            //Worst case scenario, we do not want to create duplicate.
            return true;
        }
    }

    /**
     * @param ProfileCollection $profileCollection
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     * @param bool $orderType
     *
     * @return array
     */
    protected function getFormattedProfileCollection(
        ProfileCollection $profileCollection,
        ApsisCoreHelper $apsisCoreHelper,
        StoreInterface $store,
        bool $orderType = false
    ): array {
        $formattedProfileCollectionArray = [];

        try {
            if ($orderType) {
                $profileCollection = $this->profileCollectionFactory
                    ->create()
                    ->getProfileCollectionForStore($store->getId());
            }

            foreach ($profileCollection as $profile) {
                if ($orderType) {
                    $index = $profile->getEmail();
                } else {
                    $index = $profile->getCustomerId();
                }
                $formattedProfileCollectionArray[$index] = $profile;
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $formattedProfileCollectionArray;
    }
}
