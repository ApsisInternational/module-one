<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Throwable;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Abandoned extends AbstractDb implements ResourceInterface
{
    /**
     * @inheritdoc
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
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @inheritdoc
     */
    public function truncateTable(ApsisLogHelper $apsisLogHelper)
    {
        try {
            $this->getConnection()->truncateTable($this->getMainTable());
            return true;
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function cleanupRecords(int $day, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $status = $this->getConnection()->delete(
                $this->getMainTable(),
                ["created_at < DATE_SUB(NOW(), INTERVAL ? DAY)" => $day]
            );
            if ($status) {
                $apsisCoreHelper->log(__METHOD__, [$status]);
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
