<?php

namespace Apsis\One\Model;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Cron\CollectionFactory as CronCollectionFactory;
use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\ResourceModel\ProfileBatch;
use Apsis\One\Model\ResourceModel\Abandoned;
use Apsis\One\Model\Sync\Profiles;
use Apsis\One\Model\Sync\Events;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Apsis\One\Model\Abandoned\Find;
use Apsis\One\Model\Events\Historical;

class Cron
{
    /**
     * @var CronCollectionFactory
     */
    private $cronCollectionFactory;

    /**
     * @var Find
     */
    private $abandonedFind;

    /**
     * @var Profiles
     */
    private $profileSyncModel;

    /**
     * @var Events
     */
    private $eventsSyncModel;

    /**
     * @var Event
     */
    private $eventResource;

    /**
     * @var ProfileBatch
     */
    private $profileBatchResource;

    /**
     * @var Abandoned
     */
    private $abandonedResource;

    /**
     * @var ApsisCoreHelper
     */
    private $coreHelper;

    /**
     * @var Historical
     */
    private $historicalEvents;

    /**
     * Cron constructor.
     *
     * @param CronCollectionFactory $cronCollectionFactory
     * @param Find $abandonedFind
     * @param Profiles $profiles
     * @param Events $events
     * @param Event $eventResource
     * @param ApsisCoreHelper $coreHelper
     * @param ProfileBatch $profileBatchResource
     * @param Abandoned $abandonedResource
     * @param Historical $historicalEvents
     */
    public function __construct(
        CronCollectionFactory $cronCollectionFactory,
        Find $abandonedFind,
        Profiles $profiles,
        Events $events,
        Event $eventResource,
        ApsisCoreHelper $coreHelper,
        ProfileBatch $profileBatchResource,
        Abandoned $abandonedResource,
        Historical $historicalEvents
    ) {
        $this->historicalEvents = $historicalEvents;
        $this->abandonedResource = $abandonedResource;
        $this->profileBatchResource = $profileBatchResource;
        $this->eventResource = $eventResource;
        $this->coreHelper = $coreHelper;
        $this->eventsSyncModel = $events;
        $this->profileSyncModel = $profiles;
        $this->abandonedFind = $abandonedFind;
        $this->cronCollectionFactory = $cronCollectionFactory;
    }

    /**
     * Cleanup process
     */
    public function cleanup()
    {
        if ($this->checkIfJobAlreadyRan('apsis_one_cleanup')) {
            return;
        }

        $days = $this->coreHelper->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_DEVELOPER_SETTING_CLEANUP_AFTER,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );

        if ($days) {
            $this->eventResource->cleanupRecords($days, $this->coreHelper);
            $this->abandonedResource->cleanupRecords($days, $this->coreHelper);
            $this->profileBatchResource->cleanupRecords($days, $this->coreHelper);
        }
    }

    /**
     * Sync events
     */
    public function syncEvents()
    {
        if ($this->checkIfJobAlreadyRan('apsis_one_sync_events')) {
            return;
        }

        $this->eventsSyncModel->sync($this->coreHelper);
    }

    /**
     * Sync profiles
     */
    public function syncProfiles()
    {
        if ($this->checkIfJobAlreadyRan('apsis_one_sync_profiles')) {
            return;
        }

        $this->profileSyncModel->batchAndSyncProfiles($this->coreHelper);
    }

    /**
     * Find abandoned carts
     */
    public function findAbandonedCarts()
    {
        if ($this->checkIfJobAlreadyRan('apsis_one_find_abandoned_carts')) {
            return;
        }

        $this->abandonedFind->processAbandonedCarts($this->coreHelper);
    }

    /**
     * Find past events
     */
    public function findHistoricalEvents()
    {
        if ($this->checkIfJobAlreadyRan('apsis_one_find_historical_events')) {
            return;
        }

        $this->historicalEvents->processHistoricalEvents($this->coreHelper);
    }

    /**
     * @param string $jobCode
     *
     * @return bool
     */
    private function checkIfJobAlreadyRan(string $jobCode)
    {
        $currentRunningJob = $this->cronCollectionFactory
            ->create()
            ->addFieldToFilter('job_code', $jobCode)
            ->addFieldToFilter('status', 'running')
            ->setPageSize(1);

        if (! $currentRunningJob->getSize()) {
            return false;
        }

        $jobAlreadyExecuted =  $this->cronCollectionFactory
            ->create()
            ->addFieldToFilter(
                'scheduled_at',
                $currentRunningJob->getFirstItem()->getScheduledAt()
            )
            ->addFieldToFilter('job_code', $jobCode)
            ->addFieldToFilter(
                'status',
                ['in' => ['success', 'failed']]
            );
        return (boolean) ($jobAlreadyExecuted->getSize());
    }
}
