<?php

namespace Apsis\One\Observer\Sales\Quote;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AddProduct implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $eventData = $observer->getEvent()->getData();
        //Logic

        return $this;
    }
}
