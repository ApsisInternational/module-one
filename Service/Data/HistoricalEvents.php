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
use Exception;
use Throwable;

class HistoricalEvents
{
    const FETCH_HISTORICAL_EVENTS = [
        EventModel::ORDER,
        EventModel::REVIEW,
        EventModel::WISHED,
    ];

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var EventsInterface[]
     */
    private array $eventTypeEntityInstances;

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
        $this->eventTypeEntityInstances = [
            EventModel::ORDER => $historicalOrders,
            EventModel::REVIEW => $historicalReviews,
            EventModel::WISHED => $historicalWishlist,
        ];
    }

    /**
     * @return Emulation
     */
    private function getEmulationModelInstance(): Emulation
    {
        return $this->emulationFactory->create();
    }

    /**
     * @param BaseService $baseService
     *
     * @return void
     */
    public function identifyAndFetchHistoricalEvents(BaseService $baseService): void
    {
        foreach ($baseService->getStores() as $store) {
            $emulate = $this->getEmulationModelInstance();

            try {
                $emulate->startEnvironmentEmulation($store->getId(), Area::AREA_FRONTEND, true);
                $profileCollection = $this->profileCollectionFactory
                    ->create()
                    ->getProfileCollectionForStore($store->getId());

                if ($profileCollection->getSize()) {
                    $this->fetchByType($store, $baseService, $profileCollection);
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
     * @param StoreInterface $store
     * @param BaseService $baseService
     * @param ProfileCollection $collection
     *
     * @return void
     */
    private function fetchByType(StoreInterface $store, BaseService $baseService, ProfileCollection $collection): void
    {
        foreach (self::FETCH_HISTORICAL_EVENTS as $eventType) {
            try {
                $profileCollectionArray = $this->getFormattedArrFromCollection($collection, $eventType);
                $instance = $this->eventTypeEntityInstances[$eventType];
                $instance->setFetchDuration($this->getCalculatedDuration($baseService));
                $instance->propagate($store, $baseService, $profileCollectionArray);
            } catch (Throwable $e) {
                $baseService->logError(__METHOD__, $e);
                continue;
            }
        }
    }

    /**
     * @param ProfileCollection $collection
     * @param int $eventType
     *
     * @return array
     */
    private function getFormattedArrFromCollection(ProfileCollection $collection, int $eventType): array
    {
        $collectionArray = [];
        /** @var ProfileModel $profile */
        foreach ($collection as $profile) {
            $index = ($eventType === EventModel::ORDER) ? $profile->getEmail() : $profile->getCustomerId();
            $collectionArray[$index] = $profile;
        }
        return $collectionArray;
    }

    /**
     * @param BaseService $baseService
     * @return array
     *
     * @throws Exception
     */
    private function getCalculatedDuration(BaseService $baseService): array
    {
        $toTime = $baseService->getDateTimeFromTimeAndTimeZone();
        $fromTime = clone $toTime;
        $fromTime->sub($baseService->getDateIntervalFromIntervalSpec('P4Y'));
        return [
            'from' => $fromTime->format('Y-m-d H:i:s'),
            'to' => $toTime->format('Y-m-d H:i:s'),
            'date' => true,
        ];
    }
}
