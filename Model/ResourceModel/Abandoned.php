<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Throwable;

class Abandoned extends AbstractDb
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
}
