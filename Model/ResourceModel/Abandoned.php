<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Abandoned extends AbstractDb implements ResourceInterface
{
    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_ABANDONED_TABLE, 'id');
    }

    /**
     * @param array $carts
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return int
     */
    public function insertAbandonedCarts(array $carts, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $write = $this->getConnection();
            return $write->insertMultiple($this->getMainTable(), $carts);
        } catch (Exception $e) {
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return 0;
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
            $apsisLogHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            $this->getConnection()->delete(
                $this->getMainTable(),
                ["created_at < DATE_SUB(NOW(), INTERVAL ? DAY)" => $day]
            );
        } catch (Exception $e) {
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }
}