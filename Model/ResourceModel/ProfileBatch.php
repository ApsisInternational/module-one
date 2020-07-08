<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\ProfileBatch as ProfileBatchModel;

class ProfileBatch extends AbstractDb implements ResourceInterface
{
    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE, 'id');
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
     * @param int $day
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function cleanupRecords(int $day, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $where = [
                "updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)" => $day,
                "sync_status IN(?)" => [
                    ProfileBatchModel::SYNC_STATUS_ERROR,
                    ProfileBatchModel::SYNC_STATUS_COMPLETED,
                    ProfileBatchModel::SYNC_STATUS_FAILED
                ]
            ];
            $this->getConnection()->delete($this->getMainTable(), $where);
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }
}
