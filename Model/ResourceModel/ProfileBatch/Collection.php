<?php

namespace Apsis\One\Model\ResourceModel\ProfileBatch;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\ProfileBatch as ProfileBatchResource;
use Apsis\One\Model\ProfileBatch;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_idFieldName = 'id';
        $this->_init(ProfileBatch::class, ProfileBatchResource::class);
    }
}
