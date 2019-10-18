<?php

namespace Apsis\One\Observer\Sales\Order;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Event;
use Apsis\One\Model\EventFactory;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileResourceCollectionFactory;

class Placed implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var ProfileResourceCollectionFactory
     */
    private $profileResourceCollectionFactory;

    /**
     * Placed constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventFactory $eventFactory
     * @param EventResource $eventResource
     * @param ProfileResourceCollectionFactory $profileResourceCollectionFactory
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventFactory $eventFactory,
        EventResource $eventResource,
        ProfileResourceCollectionFactory $profileResourceCollectionFactory
    ) {
        $this->profileResourceCollectionFactory = $profileResourceCollectionFactory;
        $this->eventFactory = $eventFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventResource = $eventResource;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $subscriberId = 0;
        $isSubscriber = false;

        if ($order->getCustomerIsGuest()) {
            $subscriberProfileFound = $this->profileResourceCollectionFactory->create()
                ->loadSubscriberByEmailAndStoreId($order->getCustomerEmail(), $order->getStoreId());

            if ($subscriberProfileFound === false) {
                return $this;
            }

            $subscriberId = $subscriberProfileFound->getSubscriberId();
            $isSubscriber = true;
        }

        if ($this->isOkToProceed($order->getStore(), $isSubscriber)) {
            $eventModel = $this->eventFactory->create()
                ->setEventType(Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER)
                ->setEventData($this->apsisCoreHelper->serialize($this->getDataArr($order, $subscriberId)))
                ->setCustomerId($order->getCustomerId())
                ->setSubscriberId($subscriberId)
                ->setStoreId($order->getStore()->getId())
                ->setEmail($order->getCustomerEmail())
                ->setStatus(Profile::SYNC_STATUS_PENDING);

            try {
                $this->eventResource->save($eventModel);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            }
        }

        return $this;
    }

    private function getDataArr(Order $order, $subscriberId = 0)
    {
        $items = [];
        /** @var Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $items [] = [
                'order_id' => (int) $order->getEntityId(),
                'product_id' => (int) $item->getProductId(),
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'product_url' => (string) $product->getProductUrl(),
                'product_image_url' => (string) $this->apsisCoreHelper->getProductImageUrl($product),
                'qty_ordered' => (float) $this->apsisCoreHelper->round($item->getQtyOrdered()),
                'price_amount' => (float) $this->apsisCoreHelper->round($item->getPrice()),
                'row_total_amount' => (float) $this->apsisCoreHelper->round($item->getRowTotal()),
            ];
        }

        $data = [
            'order_id' => (int) $order->getEntityId(),
            'increment_id' => (string) $order->getIncrementId(),
            'customer_id' => (int) $order->getCustomerId(),
            'subscriber_id' => (int) $subscriberId,
            'is_guest' => (boolean) $order->getCustomerIsGuest(),
            'created_at' => (string) $this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($order->getCreatedAt()),
            'website_name' => (string) $order->getStore()->getWebsite()->getName(),
            'store_name' => (string) $order->getStore()->getName(),
            'grand_total_amount' => (float) $this->apsisCoreHelper->round($order->getGrandTotal()),
            'shipping_amount' => (float) $this->apsisCoreHelper->round($order->getShippingAmount()),
            'discount_amount' => (float) $this->apsisCoreHelper->round($order->getDiscountAmount()),
            'shipping_method_name' => (string) $order->getShippingDescription(),
            'payment_method_name' => (string) $order->getPayment()->getMethod(),
            'items_count' => (int) $order->getTotalItemCount(),
            'currency_code' => (string) $order->getOrderCurrencyCode(),
            'items' => (array) $items
        ];
        return $data;
    }

    /**
     * @param Store $store
     * @param boolean $isSubscriber
     *
     * @return bool
     */
    private function isOkToProceed(Store $store, $isSubscriber = false)
    {
        $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());

        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_ORDER
        );

        $profileSync = ($isSubscriber) ? (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED
        ) : (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED
        );

        return ($account && $event && $profileSync);
    }
}
