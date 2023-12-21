<?php

namespace Apsis\One\Model\ResourceModel\Queue;

use Apsis\One\Model\ResourceModel\AbstractCollection;
use Apsis\One\Model\EventModel;
use Apsis\One\Model\QueueModel;
use Apsis\One\Model\ResourceModel\QueueResource;

class QueueCollection extends AbstractCollection
{
    const MODEL = QueueModel::class;
    const RESOURCE_MODEL = QueueResource::class;

    /**
     * @param int $storeId
     * @param array $types
     *
     * @return $this
     */
    public function getCollectionForStoreByWebhookType(int $storeId, array $types): QueueCollection
    {
        return $this->getCollection(
            ['type' => ['in' => $types], 'store_id' => $storeId, 'sync_status' => EventModel::STATUS_PENDING],
            null,
            200
        );
    }

    /**
     * @param int $storeId
     * @param int $type
     * @param int $pId
     *
     * @return QueueModel|bool
     */
    public function findPendingQueueItemForStoreByType(int $storeId, int $type, int $pId): bool|QueueModel
    {
        return $this->getFirstItemFromCollection(
            ['type' => $type, 'store_id' => $storeId, 'profile_id' => $pId, 'sync_status' => EventModel::STATUS_PENDING]
        );
    }
}
