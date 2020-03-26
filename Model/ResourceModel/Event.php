<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ProfileBatch;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;

class Event extends AbstractDb
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var array
     */
    private $cleanupTableColumnMapping = [
        Helper::APSIS_EVENT_TABLE => "updated_at",
        Helper::APSIS_ABANDONED_TABLE => "created_at",
        Helper::APSIS_PROFILE_BATCH_TABLE => "updated_at"
    ];

    /**
     * @var array
     */
    private $cleanupTableWhereClauseMapping = [
        Helper::APSIS_EVENT_TABLE => [
            'column' => 'status',
            'in' => [Profile::SYNC_STATUS_SYNCED, Profile::SYNC_STATUS_FAILED]
        ],
        Helper::APSIS_ABANDONED_TABLE => [],
        Helper::APSIS_PROFILE_BATCH_TABLE => [
            'column' => 'sync_status',
            'in' => [
                ProfileBatch::SYNC_STATUS_ERROR,
                ProfileBatch::SYNC_STATUS_COMPLETED,
                ProfileBatch::SYNC_STATUS_FAILED]
        ],
    ];

    /**
     * Event constructor.
     *
     * @param Context $context
     * @param Helper $apsisCoreHelper
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        DateTime $dateTime,
        $connectionName = null
    ) {
        $this->dateTime = $dateTime;
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(Helper::APSIS_EVENT_TABLE, 'id');
    }

    /**
     * @param array $events
     *
     * @return int
     */
    public function insertEvents(array $events)
    {
        try {
            $write = $this->getConnection();
            return $write->insertMultiple($this->getMainTable(), $events);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }

    /**
     * @param string $oldEmail
     * @param string $newEmail
     *
     * @return int
     */
    public function updateEventsEmail(string $oldEmail, string $newEmail)
    {
        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['email' => $newEmail],
                $this->getConnection()->quoteInto('email = ?', $oldEmail)
            );
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array $ids
     * @param int $status
     * @param string $msg
     *
     * @return int
     */
    public function updateSyncStatus($ids, $status, string $msg = '')
    {
        if (empty($ids)) {
            return 0;
        }

        $bind = ['status' => $status, 'updated_at' => $this->dateTime->formatDate(true)];
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }

    /**
     * @param int $day
     */
    public function cleanupRecords(int $day)
    {
        foreach ($this->cleanupTableColumnMapping as $table => $column) {
            try {
                $where = ["$column < DATE_SUB(NOW(), INTERVAL $day DAY)"];
                $mapping = $this->cleanupTableWhereClauseMapping[$table];
                if (! empty($mapping)) {
                    $whereColumn = $mapping['column'];
                    $where["$whereColumn IN(?)"] = $mapping['in'];
                }
                $this->getConnection()->delete($this->getTable($table), $where);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                continue;
            }
        }
    }
}
