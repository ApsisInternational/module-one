<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;

class Event extends AbstractDb
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * Event constructor.
     *
     * @param Context $context
     * @param Helper $apsisCoreHelper
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        DateTime $dateTime,
        $connectionName = null
    ) {
        $this->dateTime = $dateTime;
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

    /**
     * @param string $oldEmail
     * @param string $newEmail
     *
     * @return int
     */
    public function updateEventsEmail(string $oldEmail, string $newEmail)
    {
        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['email' => $newEmail],
                $this->getConnection()->quoteInto('email = ?', $oldEmail)
            );
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array $ids
     * @param int $status
     *
     * @return int
     */
    public function updateSyncStatus($ids, $status)
    {
        if (empty($ids)) {
            return 0;
        }

        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['status' => $status, 'updated_at' => $this->dateTime->formatDate(true)],
                ["id IN (?)" => $ids]
            );
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }
}
