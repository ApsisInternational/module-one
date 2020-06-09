<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Abandoned extends AbstractDb
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Abandoned constructor.
     *
     * @param Context $context
     * @param Helper $apsisCoreHelper
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        $connectionName = null
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(Helper::APSIS_ABANDONED_TABLE, 'id');
    }

    /**
     * @param array $carts
     *
     * @return int
     */
    public function insertAbandonedCarts(array $carts)
    {
        try {
            $write = $this->getConnection();
            return $write->insertMultiple($this->getMainTable(), $carts);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }

    /**
     * @return bool
     */
    public function truncateTable()
    {
        try {
            $this->getConnection()->truncateTable($this->getMainTable());
            return true;
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return false;
        }
    }
}
