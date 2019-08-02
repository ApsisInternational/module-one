<?php

namespace Apsis\One\Observer\Customer\Login;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class Checkout implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $eventData = $observer->getEvent()->getData();
        //Logic

        return $this;
    }
}
