<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\BaseService;
use Throwable;

class EventResource extends AbstractResource
{
    const RESOURCE_MODEL = BaseService::APSIS_EVENT_TABLE;

    /**
     * @param int $storeId
     * @param BaseService $service
     *
     * @return int
     */
    public function resetEventStatusForGivenStore(int $storeId, BaseService $service): int
    {
        try {
            return (int) $this->getConnection()->update(
                $this->getMainTable(),
                ['sync_status' => EventModel::STATUS_HISTORICAL],
                $this->getConnection()->quoteInto('store_id = ?', $storeId)
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }
}
