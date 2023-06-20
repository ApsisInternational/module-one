<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Magento\Framework\Event\Observer;
use Throwable;

class SaveUpdateObserver extends AbstractObserver
{
    const REGISTRY_NAME = '_customer_save_after';

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            $customer = $this->getModelFromObserver($observer, ProfileService::ENTITY_CUSTOMER);
            if (! $customer) {
                return $this;
            }

            return $this->executeForCustomerAndSubscriber($customer);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
            return $this;
        }
    }
}
