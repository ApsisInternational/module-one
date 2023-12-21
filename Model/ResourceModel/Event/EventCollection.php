<?php

namespace Apsis\One\Model\ResourceModel\Event;

use Apsis\One\Model\ResourceModel\AbstractCollection;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Model\EventModel;

class EventCollection extends AbstractCollection
{
    const MODEL = EventModel::class;
    const RESOURCE_MODEL = EventResource::class;

    /**
     * @param string $storeId
     * @param int $limit
     *
     * @return $this
     */
    public function getPendingEventsByStore(string $storeId, int $limit): EventCollection
    {
        return $this->getCollection(
            ['sync_status' => EventModel::STATUS_PENDING,'store_id' => $storeId],
            null,
            $limit
        );
    }
}
