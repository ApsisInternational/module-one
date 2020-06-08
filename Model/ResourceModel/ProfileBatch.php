<?php

namespace Apsis\One\Model\ResourceModel;

use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\Context;

class ProfileBatch extends AbstractDb
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * ProfileBatch constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
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
        $this->_init(ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE, 'id');
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
