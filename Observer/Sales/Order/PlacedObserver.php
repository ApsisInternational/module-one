<?php

namespace Apsis\One\Observer\Sales\Order;

use Apsis\One\Observer\AbstractObserver;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Throwable;

class PlacedObserver extends AbstractObserver
{
    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getOrder();
            $profile = $this->profileService
                ->getProfile(
                    (int) $order->getStoreId(),
                    (string) $order->getCustomerEmail(),
                    (int) $order->getCustomerId()
                );
            if ($profile) {
                $this->subEventService->registerOrderPlacedEvent($order, $profile, $this->profileService);
                $profile->setHasDataChanges(true);
                $this->profileService->subProfileService->profileResource->save($profile);
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }

        return $this;
    }
}
