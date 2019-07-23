<?php

namespace Apsis\One\Model\ResourceModel\Event;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Event;

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
        $this->_init(Event::class, EventResource::class);
    }
}