<?php

namespace Apsis\One\Model;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Cron\CollectionFactory as CronCollectionFactory;
use Apsis\One\Model\ResourceModel\ProfileBatch;
use Apsis\One\Model\ResourceModel\Abandoned;
use Apsis\One\Model\Sync\Profiles;
use Apsis\One\Model\Sync\Events;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Apsis\One\Model\Abandoned\Find;

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
     * Cron constructor.
     *
     * @param CronCollectionFactory $cronCollectionFactory
     * @param Find $abandonedFind
     * @param Profiles $profiles
     * @param Events $events
     * @param ApsisCoreHelper $coreHelper
     * @param ProfileBatch $profileBatchResource
     * @param Abandoned $abandonedResource
     */
    public function __construct(
        CronCollectionFactory $cronCollectionFactory,
        Find $abandonedFind,
        Profiles $profiles,
        Events $events,
        ApsisCoreHelper $coreHelper,
        ProfileBatch $profileBatchResource,
        Abandoned $abandonedResource
    ) {
        $this->abandonedResource = $abandonedResource;
        $this->profileBatchResource = $profileBatchResource;
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
        $isEnabled = $this->coreHelper->isEnabled(ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        if ($this->checkIfJobAlreadyRan('apsis_one_cleanup') || ! $isEnabled) {
            return;
        }

        $days = $this->coreHelper->getConfigValue(
            ApsisConfigHelper::DEVELOPER_SETTING_CLEANUP_AFTER,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );

        if ($days) {
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

        $this->eventsSyncModel->process($this->coreHelper);
    }

    /**
     * Sync profiles
     */
    public function syncProfiles()
    {
        if ($this->checkIfJobAlreadyRan('apsis_one_sync_profiles')) {
            return;
        }

        $this->profileSyncModel->process($this->coreHelper);
    }

    /**
     * Find abandoned carts
     */
    public function findAbandonedCarts()
    {
        if ($this->checkIfJobAlreadyRan('apsis_one_find_abandoned_carts')) {
            return;
        }

        $this->abandonedFind->process($this->coreHelper);
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

        if (! $currentRunningJob->getSize() || empty($dateTime = $currentRunningJob->getFirstItem()->getScheduledAt())) {
            return false;
        }

        $jobAlreadyExecuted =  $this->cronCollectionFactory
            ->create()
            ->addFieldToFilter(
                'scheduled_at',
                $dateTime
            )
            ->addFieldToFilter('job_code', $jobCode)
            ->addFieldToFilter(
                'status',
                ['in' => ['success', 'failed']]
            );

        $check = (boolean) ($jobAlreadyExecuted->getSize());
        if ($check) {
            $this->coreHelper->debug(__METHOD__, ['Job already ran.' => $jobCode]);
        }

        return $check;
    }
}
