<?php

namespace Apsis\One\Model\ResourceModel\Subscriber;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Subscriber as SubscriberResource;
use Apsis\One\Model\Subscriber;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Initialize resource collection.
     *
     * @return null
     */
    public function _construct()
    {
        $this->_init(Subscriber::class, SubscriberResource::class);
    }
}