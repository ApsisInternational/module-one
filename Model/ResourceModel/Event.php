<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Event extends AbstractDb
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Event constructor.
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
}
