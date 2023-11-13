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
    public function setHistoricalStatusOnAllEvents(int $storeId, BaseService $service): int
    {
        try {
            return (int) $this->getConnection()->update(
                $this->getMainTable(),
                [
                    'sync_status' => EventModel::STATUS_HISTORICAL,
                    'error_message' => '',
                    'updated_at' => $this->dateTime->formatDate(true)
                ],
                $this->getConnection()->quoteInto('store_id = ?', $storeId)
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param int $storeId
     * @param array $period
     * @param BaseService $service
     *
     * @return int
     */
    public function setPendingStatusOnHistoricalEvents(int $storeId, array $period, BaseService $service): int
    {
        try {
            return (int) $this->getConnection()->update(
                $this->getMainTable(),
                [
                    'sync_status' => EventModel::STATUS_PENDING,
                    'error_message' => '',
                    'updated_at' => $this->dateTime->formatDate(true)
                ],
                [
                    'store_id = ?' => $storeId,
                    'sync_status' => EventModel::STATUS_HISTORICAL,
                    'created_at >= ?' => $period['from'],
                    'created_at <= ?' => $period['to']
                ]
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }
}
