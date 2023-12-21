<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\ResourceModel\Profile\ProfileCollection;
use Apsis\One\Model\ResourceModel\Profile\ProfileCollectionFactory;
use Apsis\One\Service\BaseService;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Framework\App\Area;
use Throwable;

class HistoricalEvents
{
    const FETCH_HISTORICAL_EVENTS = [
        EventModel::EVENT_PLACED_ORDER,
        EventModel::EVENT_PRODUCT_REVIEWED,
        EventModel::EVENT_PRODUCT_WISHED,
    ];

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var OrderEvents
     */
    private OrderEvents $historicalOrders;

    /**
     * @var ReviewEvents
     */
    private ReviewEvents $historicalReviews;

    /**
     * @var WishlistEvents
     */
    private WishlistEvents $historicalWishlist;

    /**
     * @var EmulationFactory
     */
    private EmulationFactory $emulationFactory;

    /**
     * Historical constructor.
     *
     * @param OrderEvents $historicalOrders
     * @param ReviewEvents $historicalReviews
     * @param WishlistEvents $historicalWishlist
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EmulationFactory $emulationFactory
     */
    public function __construct(
        OrderEvents $historicalOrders,
        ReviewEvents $historicalReviews,
        WishlistEvents $historicalWishlist,
        ProfileCollectionFactory $profileCollectionFactory,
        EmulationFactory $emulationFactory
    ) {
        $this->emulationFactory = $emulationFactory;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->historicalWishlist = $historicalWishlist;
        $this->historicalOrders = $historicalOrders;
        $this->historicalReviews = $historicalReviews;
    }

    /**
     * @param BaseService $baseService
     *
     * @return void
     */
    public function identifyAndFetchHistoricalEvents(BaseService $baseService): void
    {
        $baseService->log(__METHOD__);
        foreach ($baseService->getStores() as $store) {
            $emulate = $this->getEmulationModel();
            try {
                $emulate->startEnvironmentEmulation($store->getId(), Area::AREA_FRONTEND, true);
                $profileCollection = $this->profileCollectionFactory
                    ->create()
                    ->getProfileCollectionForStore($store->getId());

                if ($profileCollection->getSize()) {
                    $this->runByType($store, $baseService, $profileCollection);
                }
            } catch (Throwable $e) {
                $baseService->logError(__METHOD__, $e);
                $emulate->stopEnvironmentEmulation();
                continue;
            }
            $emulate->stopEnvironmentEmulation();
        }
    }

    /**
     * @return Emulation
     */
    private function getEmulationModel(): Emulation
    {
        return $this->emulationFactory->create();
    }

    /**
     * @param int $entityTypeId
     *
     * @return AbstractEvents|null
     */
    private function getEventEntityObject(int $entityTypeId): ?AbstractEvents
    {
        $eventTypeObjects = [
            EventModel::EVENT_PLACED_ORDER => $this->historicalOrders,
            EventModel::EVENT_PRODUCT_REVIEWED => $this->historicalReviews,
            EventModel::EVENT_PRODUCT_WISHED => $this->historicalWishlist,
        ];
        return $eventTypeObjects[$entityTypeId] ?? null;
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $baseService
     * @param ProfileCollection $profileCollection
     *
     * @return void
     */
    private function runByType(
        StoreInterface $store,
        BaseService $baseService,
        ProfileCollection $profileCollection
    ): void {
        try {
            $toTime = $baseService->getDateTimeFromTimeAndTimeZone();
            $fromTime = clone $toTime;
            $fromTime->sub($baseService->getDateIntervalFromIntervalSpec('P4Y'));
            $fetchDuration = [
                'from' => $fromTime->format('Y-m-d H:i:s'),
                'to' => $toTime->format('Y-m-d H:i:s'),
                'date' => true,
            ];
            foreach (self::FETCH_HISTORICAL_EVENTS as $type) {
                try {
                    if ($type === EventModel::EVENT_PLACED_ORDER) {
                        $profileCollectionArray = $this
                            ->getFormattedProfileCollection($profileCollection, $baseService, $store, true);
                    } else {
                        $profileCollectionArray = $this->getFormattedProfileCollection(
                            $profileCollection,
                            $baseService,
                            $store
                        );
                    }

                    $object = $this->getEventEntityObject($type);
                    if ($object instanceof AbstractEvents) {
                        $object->setFetchDuration($fetchDuration)
                            ->process($store, $baseService, $profileCollectionArray);
                    }
                } catch (Throwable $e) {
                    $baseService->logError(__METHOD__, $e);
                    continue;
                }
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ProfileCollection $profileCollection
     * @param BaseService $baseService
     * @param StoreInterface $store
     * @param bool $orderType
     *
     * @return array
     */
    protected function getFormattedProfileCollection(
        ProfileCollection $profileCollection,
        BaseService $baseService,
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

            /** @var ProfileModel $profile */
            foreach ($profileCollection as $profile) {
                if ($orderType) {
                    $index = $profile->getEmail();
                } else {
                    $index = $profile->getCustomerId();
                }
                $formattedProfileCollectionArray[$index] = $profile;
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $formattedProfileCollectionArray;
    }
}
