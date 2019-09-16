<?php

namespace Apsis\One\Model\ResourceModel;

use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Profile extends AbstractDb
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_PROFILE_TABLE, 'id');
    }

    /**
     * Profile constructor.
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
     * @param array $subscriberIds
     * @param int $storeId
     * @param int $status
     *
     * @return int
     */
    public function updateSubscribersSyncStatus($subscriberIds, $storeId, $status)
    {
        if (empty($subscriberIds)) {
            return 0;
        }

        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['subscriber_sync_status' => $status],
                ["subscriber_id IN (?)" => $subscriberIds, "store_id = ?" => $storeId]
            );
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }
}
