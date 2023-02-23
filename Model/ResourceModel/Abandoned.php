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
    public function insertAbandonedCarts(array $carts, ApsisCoreHelper $apsisCoreHelper): int
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
    public function truncateTable(ApsisLogHelper $apsisLogHelper): bool
    {
        try {
            $this->getConnection()->truncateTable($this->getMainTable());
            return true;
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
