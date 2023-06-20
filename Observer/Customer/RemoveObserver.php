<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Magento\Framework\Event\Observer;
use Throwable;

class RemoveObserver extends AbstractObserver
{
    const REGISTRY_NAME = '_customer_delete_after';

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

            return $this->executeForCustomerAndSubscriber($customer, ProfileService::PROFILE_DELETE);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
            return $this;
        }
    }
}
