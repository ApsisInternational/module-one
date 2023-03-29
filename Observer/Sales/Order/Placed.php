<?php

namespace Apsis\One\Observer\Sales\Order;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Event;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Sales\Model\Order;
use Throwable;

class Placed implements ObserverInterface
{
    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $apsisLogHelper;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var Event
     */
    private Event $eventService;

    /**
     * @var SubscriberFactory
     */
    private SubscriberFactory $subscriberFactory;

    /**
     * Placed constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     * @param SubscriberFactory $subscriberFactory
     */
    public function __construct(
        ApsisLogHelper $apsisLogHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        Event $eventService,
        SubscriberFactory $subscriberFactory
    ) {
        $this->subscriberFactory = $subscriberFactory;
        $this->eventService = $eventService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisLogHelper = $apsisLogHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        try {
            $profile = $this->findProfile($order);
            if ($profile) {
                $this->eventService->registerOrderPlacedEvent($order, $profile);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }

        return $this;
    }

    /**
     * @param Order $order
     *
     * @return bool|DataObject|Profile
     */
    private function findProfile(Order $order)
    {
        try {
            if ($order->getCustomerId()) {
                $profile = $this->profileCollectionFactory->create()->loadByCustomerId($order->getCustomerId());
                if ($profile) {
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
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
