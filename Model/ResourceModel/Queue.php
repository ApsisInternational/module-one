<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Throwable;

class Queue extends AbstractDb
{
    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(Context $context, DateTime $dateTime, $connectionName = null)
    {
        $this->dateTime = $dateTime;
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_QUEUE_TABLE, 'id');
    }

    /**
     * @param array $ids
     * @param array $bind
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function updateQueue(array $ids, array $bind, ApsisCoreHelper $apsisCoreHelper): void
    {
        try {
            $bind = [
                'error_message' => $bind['message'],
                'processed_at' => $this->dateTime->formatDate(true),
                'sync_status' => $bind['status']
            ];
            $this->getConnection()->update($this->getMainTable(), $bind, ["id IN (?)" => $ids]);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
