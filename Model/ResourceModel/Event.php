<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Profile as ApsisProfile;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Profile;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;

class Event extends AbstractDb implements ResourceInterface
{
    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * Event constructor.
     *
     * @param Context $context
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        $connectionName = null
    ) {
        $this->dateTime = $dateTime;
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize resource.
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
    public function insertEvents(array $events, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $write = $this->getConnection();
            return $write->insertMultiple($this->getMainTable(), $events);
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
    public function updateEventsEmail(string $oldEmail, string $newEmail, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['email' => $newEmail],
                $this->getConnection()->quoteInto('email = ?', $oldEmail)
            );
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
    public function updateSyncStatus(array $ids, int $status, ApsisCoreHelper $apsisCoreHelper, string $msg = '')
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
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * @param int $day
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function cleanupRecords(int $day, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $where = [
                "updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)" => $day,
                "status IN(?)" => [Profile::SYNC_STATUS_SYNCED, Profile::SYNC_STATUS_FAILED]
            ];
            $this->getConnection()->delete($this->getMainTable(), $where);
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ApsisLogHelper $apsisLogHelper
     *
     * @return bool
     */
    public function truncateTable(ApsisLogHelper $apsisLogHelper)
    {
        try {
            $this->getConnection()->truncateTable($this->getMainTable());
            return true;
        } catch (Exception $e) {
            $apsisLogHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $storeIds
     * @param array $ids
     * @param array $where
     *
     * @return int
     */
    public function resetEvents(
        ApsisCoreHelper $apsisCoreHelper,
        array $storeIds = [],
        array $ids = [],
        array $where = []
    ) {
        try {
            if (! empty($storeIds)) {
                $where["store_id IN (?)"] = $storeIds;
            }
            if (! empty($ids)) {
                $where["id IN (?)"] = $ids;
            }
            $bind = [
                'status' => ApsisProfile::SYNC_STATUS_PENDING,
                'error_message' => '',
                'updated_at' => $this->dateTime->formatDate(true)
            ];
            return $this->getConnection()->update(
                $this->getMainTable(),
                $bind,
                $where
            );
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return 0;
        }
    }
}
