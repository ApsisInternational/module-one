<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Throwable;

class Event extends AbstractDb
{
    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * Event constructor.
     *
     * @param Context $context
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(Context $context, DateTime $dateTime, $connectionName = null)
    {
        $this->dateTime = $dateTime;
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_EVENT_TABLE, 'id');
    }

    /**
     * @param array $events
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return int
     */
    public function insertEvents(array $events, ApsisCoreHelper $apsisCoreHelper): int
    {
        try {
            $write = $this->getConnection();
            return $write->insertMultiple($this->getMainTable(), $events);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param string $oldEmail
     * @param string $newEmail
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return int
     */
    public function updateEventsEmail(string $oldEmail, string $newEmail, ApsisCoreHelper $apsisCoreHelper): int
    {
        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['email' => $newEmail],
                $this->getConnection()->quoteInto('email = ?', $oldEmail)
            );
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param array $ids
     * @param int $status
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param string $msg
     *
     * @return int
     */
    public function updateSyncStatus(array $ids, int $status, ApsisCoreHelper $apsisCoreHelper, string $msg = ''): int
    {
        if (empty($ids)) {
            return 0;
        }

        $bind = ['sync_status' => $status, 'updated_at' => $this->dateTime->formatDate(true)];
        if (strlen($msg)) {
            $bind['error_message'] = $msg;
        }

        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                $bind,
                ["id IN (?)" => $ids]
            );
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }
}
