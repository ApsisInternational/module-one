<?php

namespace Apsis\One\Observer\Customer\Address;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Magento\Framework\Event\Observer;
use Throwable;

class SaveUpdateLoadObserver extends AbstractObserver
{
    const REGISTRY_NAME = '_apsis_customer_address_load_save_after';

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            return $this->executeForCustomerAndSubscriber($observer, ProfileService::TYPE_ADDRESS);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }

        return $this;
    }
}
