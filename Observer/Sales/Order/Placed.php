<?php

namespace Apsis\One\Observer\Sales\Order;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class Placed implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $eventData = $observer->getEvent()->getData();
        //Logic

        return $this;
    }
}
