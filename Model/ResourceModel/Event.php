<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Event as EventModel;
use Apsis\One\Model\Profile as ApsisProfile;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Throwable;

class Event extends AbstractDb implements ResourceInterface
{
    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var ApsisDateHelper
     */
    private ApsisDateHelper $apsisDateHelper;

    /**
     * Event constructor.
     *
     * @param Context $context
     * @param DateTime $dateTime
     * @param ApsisDateHelper $apsisDateHelper
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        ApsisDateHelper $apsisDateHelper,
        $connectionName = null
    ) {
        $this->apsisDateHelper = $apsisDateHelper;
        $this->dateTime = $dateTime;
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_EVENT_TABLE, 'id');
    }

    /**
     * @param array $events
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return int
     */
    public function insertEvents(array $events, ApsisCoreHelper $apsisCoreHelper): int
    {
        try {
            $write = $this->getConnection();
            return $write->insertMultiple($this->getMainTable(), $events);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param string $oldEmail
     * @param string $newEmail
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return int
     */
    public function updateEventsEmail(string $oldEmail, string $newEmail, ApsisCoreHelper $apsisCoreHelper): int
    {
        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                ['email' => $newEmail],
                $this->getConnection()->quoteInto('email = ?', $oldEmail)
            );
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param array $ids
     * @param int $status
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param string $msg
     *
     * @return int
     */
    public function updateSyncStatus(array $ids, int $status, ApsisCoreHelper $apsisCoreHelper, string $msg = ''): int
    {
        if (empty($ids)) {
            return 0;
        }

        $bind = ['status' => $status, 'updated_at' => $this->dateTime->formatDate(true)];
        if (strlen($msg)) {
            $bind['error_message'] = $msg;
        }

        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                $bind,
                ["id IN (?)" => $ids]
            );
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

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $storeIds
     * @param array $ids
     * @param array $where
     *
     * @return int
     */
    public function resetEvents(
        ApsisCoreHelper $apsisCoreHelper,
        array $storeIds = [],
        array $ids = [],
        array $where = []
    ): int {
        try {
            if (! empty($storeIds)) {
                $where["store_id IN (?)"] = $storeIds;
            }
            if (! empty($ids)) {
                $where["id IN (?)"] = $ids;
            }
            $where["status != ?"] = EventModel::SYNC_STATUS_PENDING_HISTORICAL;
            $bind = [
                'status' => ApsisProfile::SYNC_STATUS_PENDING,
                'error_message' => '',
                'updated_at' => $this->dateTime->formatDate(true)
            ];
            return $this->getConnection()->update(
                $this->getMainTable(),
                $bind,
                $where
            );
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param int $configDuration
     * @param int $eventType
     * @param array $storeIds
     *
     * @return int
     */
    public function setPendingStatusOnHistoricalPendingEvents(
        ApsisCoreHelper $apsisCoreHelper,
        int $configDuration,
        int $eventType,
        array $storeIds
    ): int {
        try {
            $period = $this->getPeriod($apsisCoreHelper, $configDuration);
            if (empty($period)) {
                return 0;
            }

            $bind = [
                'status' => ApsisProfile::SYNC_STATUS_PENDING,
                'error_message' => '',
                'updated_at' => $this->dateTime->formatDate(true)
            ];
            $where = [
                'status = ?' => EventModel::SYNC_STATUS_PENDING_HISTORICAL,
                'event_type = ?' => $eventType,
                'store_id in (?)' => $storeIds,
                'created_at >= ?' => $period['from'],
                'created_at <= ?' => $period['to']
            ];
            $apsisCoreHelper->debug(__METHOD__, ['Duration' => $period]);

            return $this->getConnection()->update($this->getMainTable(), $bind, $where);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param int $configDuration
     *
     * @return array
     */
    private function getPeriod(ApsisCoreHelper $apsisCoreHelper, int $configDuration): array
    {
        try {
            $to = $this->getToDatestamp($apsisCoreHelper);
            if (empty($to)) {
                return [];
            }

            $from = $this->getFromDatestamp($configDuration, $apsisCoreHelper);
            if (empty($from)) {
                return [];
            }

            return ['from' => $from, 'to' => $to];
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param int $pastEventsDuration
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return string
     */
    private function getFromDatestamp(int $pastEventsDuration, ApsisCoreHelper $apsisCoreHelper): string
    {
        try {
            return $this->apsisDateHelper->getDateTimeFromTime()
                ->sub($this->apsisDateHelper->getDateIntervalFromIntervalSpec(sprintf('P%sM', $pastEventsDuration)))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return string
     */
    private function getToDatestamp(ApsisCoreHelper $apsisCoreHelper): string
    {
        try {
            return $this->apsisDateHelper->getDateTimeFromTime()->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return '';
        }
    }
}
