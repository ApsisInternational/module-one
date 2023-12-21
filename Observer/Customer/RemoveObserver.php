<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Magento\Framework\Event\Observer;
use Throwable;

class RemoveObserver extends AbstractObserver
{
    const REGISTRY_NAME = '_apsis_customer_delete_after';

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            return $this->executeForCustomerAndSubscriber(
                $observer,
                ProfileService::TYPE_CUSTOMER,
                ProfileService::OP_DELETE
            );
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
            return $this;
        }
    }
}
