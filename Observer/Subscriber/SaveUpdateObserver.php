<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Magento\Framework\Event\Observer;
use Throwable;

class SaveUpdateObserver extends AbstractObserver
{
    const REGISTRY_NAME = '_subscriber_subscription_change';

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            $subscriber = $this->getModelFromObserver($observer, ProfileService::ENTITY_TYPE_SUBSCRIBER);
            if (! $subscriber) {
                return $this;
            }

            return $this->executeForCustomerAndSubscriber($subscriber);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
            return $this;
        }
    }
}
