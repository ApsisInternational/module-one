<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\BaseService;
use Throwable;

class QueueResource extends AbstractResource
{
    const RESOURCE_MODEL = BaseService::APSIS_QUEUE_TABLE;

    /**
     * @param int $type
     * @param int $syncStatus
     * @param int $profileId
     * @param BaseService $service
     *
     * @return void
     */
    public function deleteItemsByStatusAndProfile(int $type, int $syncStatus, int $profileId, BaseService $service)
    {
        try {
            $this->getConnection()
                ->delete(
                    $this->getMainTable(),
                    ['type != ?' => $type, 'sync_status = ?' => $syncStatus, 'profile_id = ?' => $profileId]
                );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param int $storeId
     * @param BaseService $service
     *
     * @return void
     */
    public function deleteAllPendingItemsForStore(int $storeId, BaseService $service)
    {
        try {
            $this->getConnection()
                ->delete(
                    $this->getMainTable(),
                    ['store_id = ?' => $storeId, 'sync_status = ?' => EventModel::STATUS_PENDING]
                );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }
}
