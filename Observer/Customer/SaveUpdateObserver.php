<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Magento\Framework\Event\Observer;
use Throwable;

class SaveUpdateObserver extends AbstractObserver
{
    const REGISTRY_NAME = '_apsis_customer_save_after';

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            return $this->executeForCustomerAndSubscriber($observer, ProfileService::TYPE_CUSTOMER);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
            return $this;
        }
    }
}
