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
        EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
        EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
        EventModel::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
        EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
    ];

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var CartEvents
     */
    private CartEvents $historicalCarts;

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
     * @param CartEvents $historicalCarts
     * @param OrderEvents $historicalOrders
     * @param ReviewEvents $historicalReviews
     * @param WishlistEvents $historicalWishlist
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EmulationFactory $emulationFactory
     */
    public function __construct(
        CartEvents $historicalCarts,
        OrderEvents $historicalOrders,
        ReviewEvents $historicalReviews,
        WishlistEvents $historicalWishlist,
        ProfileCollectionFactory $profileCollectionFactory,
        EmulationFactory $emulationFactory
    ) {
        $this->emulationFactory = $emulationFactory;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->historicalWishlist = $historicalWishlist;
        $this->historicalCarts = $historicalCarts;
        $this->historicalOrders = $historicalOrders;
        $this->historicalReviews = $historicalReviews;
    }

    /**
     * @param BaseService $baseService
     *
     * @return void
     */
    public function process(BaseService $baseService): void
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
                $baseService->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
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
            EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART => $this->historicalCarts,
            EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER => $this->historicalOrders,
            EventModel::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW => $this->historicalReviews,
            EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST => $this->historicalWishlist,
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
        foreach (self::FETCH_HISTORICAL_EVENTS as $type) {
            try {
                if ($type === EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER) {
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
                    $object->process($store, $baseService, $profileCollectionArray);
                }
            } catch (Throwable $e) {
                $baseService->logError(__METHOD__, $e);
                continue;
            }
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
