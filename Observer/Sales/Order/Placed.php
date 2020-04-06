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
                'orderId' => (int) $order->getEntityId(),
                'productId' => (int) $item->getProductId(),
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'productUrl' => (string) $product->getProductUrl(),
                'productImageUrl' => (string) $this->apsisCoreHelper->getProductImageUrl($product),
                'qtyOrdered' => (float) $this->apsisCoreHelper->round($item->getQtyOrdered()),
                'priceAmount' => (float) $this->apsisCoreHelper->round($item->getPrice()),
                'rowTotalAmount' => (float) $this->apsisCoreHelper->round($item->getRowTotal()),
            ];
        }

        $data = [
            'orderId' => (int) $order->getEntityId(),
            'incrementId' => (string) $order->getIncrementId(),
            'customerId' => (int) $order->getCustomerId(),
            'subscriberId' => (int) $subscriberId,
            'isGuest' => (boolean) $order->getCustomerIsGuest(),
            'createdAt' => (int) $this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($order->getCreatedAt()),
            'websiteName' => (string) $order->getStore()->getWebsite()->getName(),
            'storeName' => (string) $order->getStore()->getName(),
            'grandTotalAmount' => (float) $this->apsisCoreHelper->round($order->getGrandTotal()),
            'shippingAmount' => (float) $this->apsisCoreHelper->round($order->getShippingAmount()),
            'discountAmount' => (float) $this->apsisCoreHelper->round($order->getDiscountAmount()),
            'shippingMethodName' => (string) $order->getShippingDescription(),
            'paymentMethodName' => (string) $order->getPayment()->getMethod(),
            'itemsCount' => (int) $order->getTotalItemCount(),
            'currencyCode' => (string) $order->getOrderCurrencyCode(),
            'items' => $items
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
