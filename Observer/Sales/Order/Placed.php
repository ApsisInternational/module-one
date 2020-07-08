<?php

namespace Apsis\One\Observer\Sales\Order;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\Event;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;

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
     * Placed constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param Event $eventService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        Event $eventService
    ) {
        $this->eventService = $eventService;
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
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
        if (! $this->isOkToProceed($order->getStore())) {
            return $this;
        }

        try {
            if ($order->getCustomerIsGuest()) {
                $profile = $this->profileCollectionFactory->create()
                    ->loadSubscriberByEmailAndStoreId($order->getCustomerEmail(), $order->getStoreId());
                if (! $profile) {
                    return $this;
                }
            } else {
                /** @var Profile $profile */
                $profile = $this->profileCollectionFactory->create()
                    ->loadByEmailAndStoreId(
                        $order->getCustomerEmail(),
                        $order->getStore()->getId()
                    );
                $profile->setCustomerSyncStatus(Profile::SYNC_STATUS_PENDING);
            }
            $this->eventService->registerOrderPlacedEvent($order, $profile);
            $this->profileResource->save($profile);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }

        return $this;
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
