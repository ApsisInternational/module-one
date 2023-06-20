<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Service\BaseService;
use Throwable;

class EventResource extends AbstractResource
{
    const RESOURCE_MODEL = BaseService::APSIS_EVENT_TABLE;

    /**
     * @param string $oldEmail
     * @param string $newEmail
     * @param BaseService $service
     *
     * @return int
     */
    public function updateEventsEmail(string $oldEmail, string $newEmail, BaseService $service): int
    {
        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['email' => $newEmail],
                $this->getConnection()->quoteInto('email = ?', $oldEmail)
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }
}
