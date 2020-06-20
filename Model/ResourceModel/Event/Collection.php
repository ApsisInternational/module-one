<?php

namespace Apsis\One\Model\ResourceModel\Event;

use Apsis\One\Model\Profile;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Event;
use Magento\Framework\Data\Collection as FrameworkDataCollection;

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

    /**
     * @param int $storeId
     * @param int $eventType
     *
     * @return string
     */
    public function getTimestampFromFirstEventEntryByStore(int $storeId, int $eventType)
    {
        $collection = $this->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('event_type', $eventType)
            ->setOrder('created_at', FrameworkDataCollection::SORT_ORDER_ASC)
            ->setPageSize(1);
        if ($collection->getSize()) {
            return (string) $collection->getFirstItem()->getCreatedAt();
        }
        return '';
    }
}
