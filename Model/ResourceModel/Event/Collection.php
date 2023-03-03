<?php

namespace Apsis\One\Model\ResourceModel\Event;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Event;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_idFieldName = 'id';
        $this->_init(Event::class, EventResource::class);
    }

    /**
     * @param string $storeId
     * @param int $syncLimit
     *
     * @return Collection
     */
    public function getPendingEventsByStore(string $storeId, int $syncLimit): Collection
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('sync_status', Event::STATUS_PENDING)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize($syncLimit);
    }
}
