<?php

namespace Apsis\One\Model;

use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Subscriber as SubscriberResource;;

class Subscriber extends AbstractModel
{
    /**
     * Constructor.
     *
     * @return null
     */
    public function _construct()
    {
        $this->_init(SubscriberResource::class);
    }
}