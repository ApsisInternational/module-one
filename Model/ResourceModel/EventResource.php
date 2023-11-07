<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\BaseService;
use Throwable;

class EventResource extends AbstractResource
{
    const RESOURCE_MODEL = BaseService::APSIS_EVENT_TABLE;

    /**
     * @param int $profileId
     * @param string $newEmail
     * @param BaseService $service
     *
     * @return int
     */
    public function updateEventsEmail(int $profileId, string $newEmail, BaseService $service): int
    {
        try {
            return (int) $this->getConnection()->update(
                $this->getMainTable(),
                ['email' => $newEmail],
                $this->getConnection()->quoteInto('profile_id = ?', $profileId)
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param int $storeId
     * @param BaseService $service
     *
     * @return int
     */
    public function resetEventStatusForGivenStore(int $storeId, BaseService $service)
    {
        try {
            return (int) $this->getConnection()->update(
                $this->getMainTable(),
                ['sync_status' => EventModel::STATUS_PENDING], // @todo something for historical
                $this->getConnection()->quoteInto('store_id = ?', $storeId)
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }
}
