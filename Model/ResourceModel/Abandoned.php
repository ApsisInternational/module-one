<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Helper\Log as ApsisLogHelper;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;

class Abandoned extends AbstractDb
{
    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(Helper::APSIS_ABANDONED_TABLE, 'id');
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
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
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
            $apsisLogHelper->logMessage(__METHOD__, $e->getMessage());
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
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
        }
    }
}
