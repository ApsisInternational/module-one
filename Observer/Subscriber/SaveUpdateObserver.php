<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Magento\Framework\Event\Observer;
use Throwable;

class SaveUpdateObserver extends AbstractObserver
{
    const REGISTRY_NAME = '_apsis_subscriber_subscription_change';

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            return $this->executeForCustomerAndSubscriber($observer, ProfileService::TYPE_SUBSCRIBER);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
            return $this;
        }
    }
}
