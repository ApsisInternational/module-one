<?php

namespace Apsis\One\Service\Sub;

use Apsis\One\Model\EventModel;
use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Apsis\One\Service\Data\Cart\CartData;
use Apsis\One\Service\Data\Order\OrderData;
use Apsis\One\Service\Data\Review\ReviewData;
use Apsis\One\Service\Data\Wishlist\WishlistData;
use Apsis\One\Service\EventService;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Logger as CustomerLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Magento\Newsletter\Model\Subscriber;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Review\Model\Review;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\EventModelFactory;
use Magento\Wishlist\Model\Item as MagentoWishlistItem;
use Magento\Wishlist\Model\Wishlist;
use Throwable;

class SubEventService
{
    /**
     * @var EventResource
     */
    public EventResource $eventResource;

    /**
     * @var EventModelFactory
     */
    private EventModelFactory $eventModelFactory;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var CartData
     */
    private CartData $cartData;

    /**
     * @var OrderData
     */
    private OrderData $orderData;

    /**
     * @var WishlistData
     */
    private WishlistData $wishlistData;

    /**
     * @var ReviewData
     */
    private ReviewData $reviewData;

    /**
     * @param EventModelFactory $eventModelFactory
     * @param EventResource $eventResource
     * @param Registry $registry
     * @param CartData $cartData
     * @param OrderData $orderData
     * @param WishlistData $wishlistData
     * @param ReviewData $reviewData
     */
    public function __construct(
        EventModelFactory $eventModelFactory,
        EventResource $eventResource,
        Registry $registry,
        CartData $cartData,
        OrderData $orderData,
        WishlistData $wishlistData,
        ReviewData $reviewData
    ) {
        $this->reviewData = $reviewData;
        $this->wishlistData = $wishlistData;
        $this->orderData = $orderData;
        $this->cartData = $cartData;
        $this->registry = $registry;
        $this->eventModelFactory = $eventModelFactory;
        $this->eventResource = $eventResource;
    }

    /**
     * @param CustomerLogger $logger
     * @param int $customerId
     * @param ProfileModel $profile
     * @param CustomerInterface $customer
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerLoggedInEvent(
        CustomerLogger $logger,
        int $customerId,
        ProfileModel $profile,
        CustomerInterface $customer,
        BaseService $baseService
    ): void {
        try {
            $customerLog = $logger->get($customerId);
            $this->registerEvent(
                EventModel::EVENT_LOGGED_IN,
                [
                    'customerId' => (int) $customerLog->getCustomerId(),
                    'lastLogoutAt' => (int) $baseService
                        ->formatDateForPlatformCompatibility($customerLog->getLastLogoutAt()),
                    'lastVisitAt' => (int) $baseService
                        ->formatDateForPlatformCompatibility($customerLog->getLastVisitAt()),
                    'websiteName' => (string) $baseService->getStoreWebsiteName($customer->getStoreId()),
                    'storeName' => (string) $baseService->getStoreName($customer->getStoreId())
                ],
                $baseService,
                (int) $profile->getId(),
                (string) $customer->getEmail(),
                (int) $baseService->getStore()->getId(),
                (int) $customerLog->getCustomerId()
            );
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Customer $customer
     * @param ProfileModel $profile
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerSubscriberBecomesCustomerEvent(
        Customer $customer,
        ProfileModel $profile,
        BaseService $baseService
    ): void {
        try {
            if ($profile->getIsSubscriber() && ! $profile->getIsCustomer()) {
                $this->registerEvent(
                    EventModel::EVENT_SUBSCRIBER_BECOMES_CUSTOMER,
                    [
                        'subscriberId' => (int) $profile->getSubscriberId(),
                        'customerId' => (int) $customer->getEntityId(),
                        'websiteName' => (string) $baseService->getStoreWebsiteName($customer->getStoreId()),
                        'storeName' => (string) $baseService->getStoreName($customer->getStoreId())
                    ],
                    $baseService,
                    (int) $profile->getId(),
                    (string) $customer->getEmail(),
                    (int) $customer->getStoreId(),
                    (int) $customer->getId(),
                    (int) $profile->getSubscriberId()
                );
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Subscriber $subscriber
     * @param ProfileModel $profile
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerCustomerBecomesSubscriberEvent(
        Subscriber $subscriber,
        ProfileModel $profile,
        BaseService $baseService
    ): void {
        if ($profile->getIsCustomer() && ! $profile->getIsSubscriber()) {
            $this->registerEvent(
                EventModel::EVENT_CUSTOMER_BECOMES_SUBSCRIBER,
                [
                    'subscriberId' => (int) $subscriber->getSubscriberId(),
                    'customerId' => (int) $profile->getCustomerId(),
                    'websiteName' => (string) $baseService->getStoreWebsiteName($subscriber->getStoreId()),
                    'storeName' => (string) $baseService->getStoreName($subscriber->getStoreId())
                ],
                $baseService,
                (int) $profile->getId(),
                (string) $subscriber->getEmail(),
                (int) $subscriber->getStoreId(),
                (int) $profile->getCustomerId(),
                (int) $subscriber->getSubscriberId()
            );
        }
    }

    /**
     * @param Subscriber $subscriber
     * @param ProfileModel $profile
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerSubscriptionChangedEvent(
        Subscriber $subscriber,
        ProfileModel $profile,
        BaseService $baseService
    ): void {
        try {
            if ($subscriber->getSubscriberStatus() !== Subscriber::STATUS_UNSUBSCRIBED) {
                return;
            }

            $emailReg = $this->registry
                ->registry($subscriber->getEmail() . EventService::REGISTRY_NAME_SUBSCRIBER_UPDATE);
            if ($emailReg) {
                return;
            }
            $this->registry->unregister($subscriber->getEmail() . EventService::REGISTRY_NAME_SUBSCRIBER_UPDATE);
            $this->registry->register(
                $subscriber->getEmail() . EventService::REGISTRY_NAME_SUBSCRIBER_UPDATE,
                $subscriber->getEmail(),
                true
            );

            $this->registerEvent(
                EventModel::EVENT_SUBSCRIBER_UNSUBSCRIBED,
                [
                    'subscriberId' => (int) $subscriber->getSubscriberId(),
                    'websiteName' => (string) $baseService->getStoreWebsiteName($subscriber->getStoreId()),
                    'storeName' => (string) $baseService->getStoreName($profile->getStoreId())
                ],
                $baseService,
                (int) $profile->getId(),
                (string) $subscriber->getEmail(),
                (int) $subscriber->getStoreId(),
                0,
                (int) $subscriber->getSubscriberId()
            );
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Quote $cart
     * @param Item $item
     * @param ProfileModel $profile
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerProductCartedEvent(
        Quote $cart,
        Item $item,
        ProfileModel $profile,
        BaseService $baseService
    ): void {
        try {
            $eventData = $this->cartData->getCartedData($cart, $item, $baseService);
            if (! empty($eventData)) {
                $this->registerEvent(
                    EventModel::EVENT_PRODUCT_CARTED,
                    $eventData,
                    $baseService,
                    (int) $profile->getId(),
                    (string) $cart->getCustomerEmail(),
                    (int) $cart->getStore()->getId(),
                    (int) $cart->getCustomerId(),
                    (int) $profile->getSubscriberId()
                );
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Order $order
     * @param ProfileModel $profile
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerOrderPlacedEvent(Order $order, ProfileModel $profile, BaseService $baseService): void
    {
        try {
            $dataArr = $this->orderData->getDataArr($order, $profile, $baseService);
            if (! empty($dataArr)) {
                $items = $dataArr['items'];
                unset($dataArr['items']);
                $this->registerEvent(
                    EventModel::EVENT_PLACED_ORDER,
                    $dataArr,
                    $baseService,
                    (int) $profile->getId(),
                    (string) $order->getCustomerEmail(),
                    (int) $order->getStore()->getId(),
                    (int) $order->getCustomerId(),
                    (int) $profile->getSubscriberId(),
                    $items
                );
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Wishlist $wishlist
     * @param MagentoWishlistItem $wishlistItem
     * @param Observer $observer
     * @param StoreInterface $store
     * @param ProfileModel $profile
     * @param Customer $customer
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerProductWishedEvent(
        Wishlist $wishlist,
        MagentoWishlistItem $wishlistItem,
        Observer $observer,
        StoreInterface $store,
        ProfileModel $profile,
        Customer $customer,
        BaseService $baseService
    ): void {
        try {
            /** @var Product $product */
            $product = $observer->getEvent()->getProduct();
            if (empty($product)) {
                return;
            }

            $eventData = $this->wishlistData
                ->getWishedData($wishlist, $wishlistItem, $store->getId(), $product, $baseService);
            if (! empty($eventData)) {
                $this->registerEvent(
                    EventModel::EVENT_PRODUCT_WISHED,
                    $eventData,
                    $baseService,
                    (int) $profile->getId(),
                    (string) $customer->getEmail(),
                    (int) $store->getId(),
                    (int) $customer->getId()
                );
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Review $reviewObject
     * @param Product $product
     * @param ProfileModel $profile
     * @param Customer $customer
     * @param BaseService $baseService
     *
     * @return void
     */
    public function registerProductReviewedEvent(
        Review $reviewObject,
        Product $product,
        ProfileModel $profile,
        Customer $customer,
        BaseService $baseService
    ): void {
        try {
            $eventData = $this->reviewData->getReviewData($reviewObject, $product, $baseService);
            if (! empty($eventData)) {
                $this->registerEvent(
                    EventModel::EVENT_PRODUCT_REVIEWED,
                    $eventData,
                    $baseService,
                    (int) $profile->getId(),
                    (string) $customer->getEmail(),
                    (int) $reviewObject->getStoreId(),
                    (int) $customer->getId()
                );
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @return EventModel
     */
    private function getEventModel(): EventModel
    {
        return $this->eventModelFactory->create();
    }

    /**
     * @param int $eventType
     * @param array $eventData
     * @param BaseService $baseService
     * @param int $profileId
     * @param string $email
     * @param int $storeId
     * @param int $customerId
     * @param int $subscriberId
     * @param array $subEventData
     *
     * @return void
     */
    private function registerEvent(
        int $eventType,
        array $eventData,
        BaseService $baseService,
        int $profileId,
        string $email,
        int $storeId,
        int $customerId = 0,
        int $subscriberId = 0,
        array $subEventData = []
    ): void {
        try {
            $eventModel = $this->getEventModel();
            $eventModel->setType($eventType)
                ->setEventData(json_encode($eventData))
                ->setProfileId($profileId)
                ->setCustomerId($customerId)
                ->setSubscriberId($subscriberId)
                ->setStoreId($storeId)
                ->setEmail($email)
                ->setSyncStatus(EventModel::STATUS_PENDING);
            if (! empty($subEventData)) {
                $eventModel->setSubEventData(json_encode($subEventData));
            }
            $this->eventResource->save($eventModel);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }
}
