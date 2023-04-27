<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\Event as EventModel;
use Apsis\One\Model\EventFactory as EventModelFactory;
use Apsis\One\Model\Events\Historical\Carts\Data as CartData;
use Apsis\One\Model\Events\Historical\Orders\Data as OrderData;
use Apsis\One\Model\Events\Historical\Reviews\Data as ReviewData;
use Apsis\One\Model\Events\Historical\Wishlist\Data as WishlistData;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
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
use Magento\Wishlist\Model\Item as WishlistItem;
use Magento\Wishlist\Model\Wishlist;
use Throwable;

class Event
{
    const REGISTRY_NAME_SUBSCRIBER_UNSUBSCRIBE = '_subscriber_save_after';

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var ApsisDateHelper
     */
    private Date $apsisDateHelper;

    /**
     * @var EventModelFactory
     */
    private EventModelFactory $eventFactory;

    /**
     * @var ApsisCoreHelper
     */
    private Core $apsisCoreHelper;

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
     * Event constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventModelFactory $eventFactory
     * @param EventResource $eventResource
     * @param Date $apsisDateHelper
     * @param Registry $registry
     * @param CartData $cartData
     * @param OrderData $orderData
     * @param WishlistData $wishlistData
     * @param ReviewData $reviewData
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventModelFactory $eventFactory,
        EventResource $eventResource,
        ApsisDateHelper $apsisDateHelper,
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
        $this->apsisDateHelper = $apsisDateHelper;
        $this->eventFactory = $eventFactory;
        $this->eventResource = $eventResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param CustomerLogger $logger
     * @param int $customerId
     * @param Profile $profile
     * @param CustomerInterface $customer
     *
     * @return void
     */
    public function registerCustomerLoginEvent(
        CustomerLogger $logger,
        int $customerId,
        Profile $profile,
        CustomerInterface $customer
    ): void {
        $customerLog = $logger->get($customerId);
        $this->registerEvent(
            EventModel::EVENT_TYPE_CUSTOMER_LOGIN,
            [
                'customerId' => (int) $customerLog->getCustomerId(),
                'lastLogoutAt' => (int) $this->apsisDateHelper
                    ->formatDateForPlatformCompatibility($customerLog->getLastLogoutAt()),
                'lastVisitAt' => (int) $this->apsisDateHelper
                    ->formatDateForPlatformCompatibility($customerLog->getLastVisitAt()),
                'websiteName' => (string) $this->apsisCoreHelper->getStoreWebsiteName($customer->getStoreId()),
                'storeName' => (string) $this->apsisCoreHelper->getStoreName($customer->getStoreId())
            ],
            (int) $profile->getId(),
            (string) $customer->getEmail(),
            (int) $this->apsisCoreHelper->getStore()->getId(),
            (int) $customerLog->getCustomerId()
        );
    }

    /**
     * @param Subscriber $subscriber
     * @param Profile $profile
     *
     * @return void
     */
    public function registerCustomerBecomesSubscriberEvent(Subscriber $subscriber, Profile $profile): void
    {
        if ($profile->getIsCustomer()) {
            $this->registerEvent(
                EventModel::EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER,
                [
                    'subscriberId' => (int) $subscriber->getSubscriberId(),
                    'customerId' => (int) $profile->getCustomerId(),
                    'websiteName' => (string) $this->apsisCoreHelper->getStoreWebsiteName($subscriber->getStoreId()),
                    'storeName' => (string) $this->apsisCoreHelper->getStoreName($subscriber->getStoreId())
                ],
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
     * @param Profile $profile
     *
     * @return void
     */
    public function registerSubscriberUnsubscribeEvent(Subscriber $subscriber, Profile $profile): void
    {
        $emailReg = $this->registry->registry($subscriber->getEmail() . self::REGISTRY_NAME_SUBSCRIBER_UNSUBSCRIBE);
        if ($emailReg) {
            return;
        }
        $this->registry->unregister($subscriber->getEmail() . self::REGISTRY_NAME_SUBSCRIBER_UNSUBSCRIBE);
        $this->registry->register(
            $subscriber->getEmail() . self::REGISTRY_NAME_SUBSCRIBER_UNSUBSCRIBE,
            $subscriber->getEmail(),
            true
        );

        $this->registerEvent(
            EventModel::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE,
            [
                'subscriberId' => (int) $subscriber->getSubscriberId(),
                'websiteName' => (string) $this->apsisCoreHelper->getStoreWebsiteName($subscriber->getStoreId()),
                'storeName' => (string) $this->apsisCoreHelper->getStoreName($subscriber->getStoreId())
            ],
            (int) $profile->getId(),
            (string) $subscriber->getEmail(),
            (int) $subscriber->getStoreId(),
            0,
            (int) $subscriber->getSubscriberId()
        );
    }

    /**
     * @param Quote $cart
     * @param Item $item
     * @param Profile $profile
     *
     * @return void
     */
    public function registerProductCartedEvent(Quote $cart, Item $item, Profile $profile): void
    {
        $eventData = $this->cartData->getDataArr($cart, $item, $this->apsisCoreHelper);
        if (! empty($eventData)) {
            $this->registerEvent(
                EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
                $eventData,
                (int) $profile->getId(),
                (string) $cart->getCustomerEmail(),
                (int) $cart->getStore()->getId(),
                (int) $cart->getCustomerId(),
                (int) $profile->getSubscriberId()
            );
        }
    }

    /**
     * @param Order $order
     * @param Profile $profile
     *
     * @return void
     */
    public function registerOrderPlacedEvent(Order $order, Profile $profile): void
    {
        $mainData = $this->orderData->getDataArr($order, $this->apsisCoreHelper, (int) $profile->getSubscriberId());
        if (! empty($mainData) && ! empty($mainData['items'])) {
            $subData = $mainData['items'];
            unset($mainData['items']);
            $this->registerEvent(
                EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
                $mainData,
                (int) $profile->getId(),
                (string) $order->getCustomerEmail(),
                (int) $order->getStore()->getId(),
                (int) $order->getCustomerId(),
                (int) $profile->getSubscriberId(),
                $subData
            );
        }
    }

    /**
     * @param Customer $customer
     * @param Profile $profile
     *
     * @return void
     */
    public function registerSubscriberBecomesCustomerEvent(Customer $customer, Profile $profile): void
    {
        if ($profile->getIsSubscriber() && ! $profile->getIsCustomer()) {
            $eventData = [
                'subscriberId' => (int) $profile->getSubscriberId(),
                'customerId' => (int) $customer->getEntityId(),
                'websiteName' => (string) $this->apsisCoreHelper->getStoreWebsiteName($customer->getStoreId()),
                'storeName' => (string) $this->apsisCoreHelper->getStoreName($customer->getStoreId())
            ];
            $this->registerEvent(
                EventModel::EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER,
                $eventData,
                (int) $profile->getId(),
                (string) $customer->getEmail(),
                (int) $customer->getStoreId(),
                (int) $customer->getId(),
                (int) $profile->getSubscriberId()
            );
        }
    }

    /**
     * @param Observer $observer
     * @param Wishlist $wishlist
     * @param StoreInterface $store
     * @param Profile $profile
     * @param CustomerInterface $customer
     *
     * @return void
     */
    public function registerWishlistEvent(
        Observer $observer,
        Wishlist $wishlist,
        StoreInterface $store,
        Profile $profile,
        CustomerInterface $customer
    ): void {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();
        if (empty($product)) {
            return;
        }

        /** @var WishlistItem $item */
        $item = $observer->getEvent()->getItem();
        $eventData = $this->wishlistData->getDataArr($wishlist, $store, $item, $product, $this->apsisCoreHelper);
        if (! empty($eventData)) {
            $this->registerEvent(
                EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
                $eventData,
                (int) $profile->getId(),
                (string) $customer->getEmail(),
                (int) $store->getId(),
                (int) $customer->getId()
            );
        }
    }

    /**
     * @param Review $reviewObject
     * @param Product $product
     * @param Profile $profile
     * @param CustomerInterface $customer
     *
     * @return void
     */
    public function registerProductReviewEvent(
        Review $reviewObject,
        Product $product,
        Profile $profile,
        CustomerInterface $customer
    ): void {
        $eventData = $this->reviewData->getDataArr($reviewObject, $product, $this->apsisCoreHelper);
        if (! empty($eventData)) {
            $this->registerEvent(
                EventModel::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
                $eventData,
                (int) $profile->getId(),
                (string) $customer->getEmail(),
                (int) $reviewObject->getStoreId(),
                (int) $customer->getId()
            );
        }
    }

    /**
     * @param int $eventType
     * @param array $eventData
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
        int $profileId,
        string $email,
        int $storeId,
        int $customerId = 0,
        int $subscriberId = 0,
        array $subEventData = []
    ): void {
        try {
            /** @var EventModel $eventModel */
            $eventModel = $this->eventFactory->create();
            $eventModel->setType($eventType)
                ->setEventData($this->apsisCoreHelper->serialize($eventData))
                ->setProfileId($profileId)
                ->setCustomerId($customerId)
                ->setSubscriberId($subscriberId)
                ->setStoreId($storeId)
                ->setEmail($email)
                ->setSyncStatus(EventModel::STATUS_PENDING);
            if (! empty($subEventData)) {
                $eventModel->setSubEventData($this->apsisCoreHelper->serialize($subEventData));
            }
            $this->eventResource->save($eventModel);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Profile $profile
     * @param Customer $customer
     *
     * @return void
     */
    public function updateEmailInEventsForCustomer(Profile $profile, Customer $customer): void
    {
        $this->eventResource->updateEventsEmail(
            $profile->getEmail(),
            $customer->getEmail(),
            $this->apsisCoreHelper
        );
    }
}
