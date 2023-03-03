<?php

namespace Apsis\One\Model\Events;

use Apsis\One\Model\Event;
use Apsis\One\Model\Events\Historical\Carts;
use Apsis\One\Model\Events\Historical\Orders;
use Apsis\One\Model\Events\Historical\Reviews;
use Apsis\One\Model\Events\Historical\Wishlist;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Framework\App\Area;
use Throwable;

class Historical
{
    const FETCH_HISTORICAL_EVENTS = [
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
    ];

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
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EmulationFactory $emulationFactory
     */
    public function __construct(
        Carts $historicalCarts,
        Orders $historicalOrders,
        Reviews $historicalReviews,
        Wishlist $historicalWishlist,
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
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function process(ApsisCoreHelper $apsisCoreHelper): void
    {
        foreach ($apsisCoreHelper->getStores() as $store) {
            $emulate = $this->emulationFactory->create();
            try {
                $emulate->startEnvironmentEmulation($store->getId(), Area::AREA_FRONTEND, true);
                $profileCollection = $this->profileCollectionFactory
                    ->create()
                    ->getProfileCollectionForStore($store->getId());

                if ($profileCollection->getSize()) {
                    $this->runByType($store, $apsisCoreHelper, $profileCollection);
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
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollection $profileCollection
     *
     * @return void
     */
    private function runByType(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection
    ): void {
        foreach (self::FETCH_HISTORICAL_EVENTS as $type) {
            try {
                $profileCollectionArray = $this->getFormattedProfileCollection(
                    $profileCollection,
                    $apsisCoreHelper,
                    $store
                );

                switch ($type) {
                    case Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST:
                        $this->historicalWishlist->fetchForStore(
                            $store,
                            $apsisCoreHelper,
                            $profileCollection,
                            $profileCollectionArray
                        );
                        break;
                    case Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW:
                        $this->historicalReviews->fetchForStore(
                            $store,
                            $apsisCoreHelper,
                            $profileCollection,
                            $profileCollectionArray
                        );
                        break;
                    case Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART:
                        $this->historicalCarts->fetchForStore(
                            $store,
                            $apsisCoreHelper,
                            $profileCollection,
                            $profileCollectionArray
                        );
                        break;
                    case Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER:
                        $this->historicalOrders->fetchForStore(
                            $store,
                            $apsisCoreHelper,
                            $profileCollection,
                            $this->getFormattedProfileCollection($profileCollection, $apsisCoreHelper, $store, true)
                        );
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

            /** @var Profile $profile */
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
