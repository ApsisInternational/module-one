<?php

namespace Apsis\One\Model\ResourceModel\Queue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\Queue;
use Apsis\One\Model\ResourceModel\Queue as QueueResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_idFieldName = 'id';
        $this->_init(Queue::class, QueueResource::class);
    }
}
