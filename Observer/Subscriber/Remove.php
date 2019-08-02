<?php

namespace Apsis\One\Observer\Subscriber;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class Remove implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $eventData = $observer->getEvent()->getData();
        //Logic

        return $this;
    }
}
