<?php

namespace Apsis\One\Observer\Sales\Order;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Event;
use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class Placed implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var Event
     */
    private $eventService;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * Placed constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param Event $eventService
     * @param SubscriberFactory $subscriberFactory
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        Event $eventService,
        SubscriberFactory $subscriberFactory
    ) {
        $this->subscriberFactory = $subscriberFactory;
        $this->eventService = $eventService;
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        if (! $this->isOkToProceed($order->getStore())) {
            return $this;
        }

        try {
            $profile = $this->findProfile($order);
            if ($profile) {
                $this->eventService->registerOrderPlacedEvent($order, $profile);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $this;
    }

    /**
     * @param Order $order
     *
     * @return bool|DataObject
     */
    private function findProfile(Order $order)
    {
        try {
            if ($order->getCustomerId()) {
                $profile = $this->profileCollectionFactory->create()->loadByCustomerId($order->getCustomerId());
                if ($profile) {
                    $profile->setCustomerSyncStatus(Profile::SYNC_STATUS_PENDING);
                    $this->profileResource->save($profile);
                    return $profile;
                }
            }

            $subscriber = $this->subscriberFactory->create()->loadByEmail($order->getCustomerEmail());
            if ($subscriber->getId()) {
                $found = $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getId());
                if ($found) {
                    return $found;
                }
            }

            return false;
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return false;
        }
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
            ApsisConfigHelper::EVENTS_CUSTOMER_ORDER
        );

        return ($account && $event);
    }
}
