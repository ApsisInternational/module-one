<?php

namespace Apsis\One\Model\ResourceModel\Event;

use Apsis\One\Model\Profile;
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
     */
    public function _construct()
    {
        $this->_init(Event::class, EventResource::class);
    }

    /**
     * @param string $storeId
     * @param int $syncLimit
     *
     * @return Collection
     */
    public function getPendingEventsByStore(string $storeId, int $syncLimit)
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('status', Profile::SYNC_STATUS_PENDING)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize($syncLimit);
    }
}
