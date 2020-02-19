<?php

namespace Apsis\One\Observer\Sales\Order;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Event;
use Apsis\One\Model\EventFactory;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * Placed constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventFactory $eventFactory
     * @param EventResource $eventResource
     * @param ProfileResourceCollectionFactory $profileResourceCollectionFactory
     * @param ProfileResource $profileResource
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventFactory $eventFactory,
        EventResource $eventResource,
        ProfileResourceCollectionFactory $profileResourceCollectionFactory,
        ProfileResource $profileResource
    ) {
        $this->profileResource = $profileResource;
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

        if ($order->getCustomerIsGuest()) {
            $profile = $this->profileResourceCollectionFactory->create()
                ->loadSubscriberByEmailAndStoreId($order->getCustomerEmail(), $order->getStoreId());
            if (! $profile) {
                return $this;
            }
        } else {
            /** @var Profile $profile */
            $profile = $this->apsisCoreHelper->getProfileByEmailAndStoreId(
                $order->getCustomerEmail(),
                $order->getStore()->getId()
            );
            $profile->setCustomerSyncStatus(Profile::SYNC_STATUS_PENDING);
        }

        if ($this->isOkToProceed($order->getStore())) {
            try {
                $mainData = $this->getDataArr($order, $profile->getSubscriberId());
                $subData = $mainData['items'];
                unset($mainData['items']);
                $eventModel = $this->eventFactory->create()
                    ->setEventType(Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER)
                    ->setEventData($this->apsisCoreHelper->serialize($mainData))
                    ->setSubEventData($this->apsisCoreHelper->serialize($subData))
                    ->setProfileId($profile->getId())
                    ->setCustomerId($order->getCustomerId())
                    ->setSubscriberId($profile->getSubscriberId())
                    ->setStoreId($order->getStore()->getId())
                    ->setEmail($order->getCustomerEmail())
                    ->setStatus(Profile::SYNC_STATUS_PENDING);

                    $this->eventResource->save($eventModel);

                if ($profile->hasDataChanges()) {
                    $this->profileResource->save($profile);
                }
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            }
        }

        return $this;
    }

    /**
     * @param Order $order
     * @param int $subscriberId
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
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
     *
     * @return bool
     */
    private function isOkToProceed(Store $store)
    {
        $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());
        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_ORDER
        );

        return ($account && $event);
    }
}
