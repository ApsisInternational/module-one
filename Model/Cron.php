<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\Cron\CollectionFactory as CronCollectionFactory;
use Apsis\One\Model\Sync\Profiles;
use Apsis\One\Model\Sync\Events;

class Cron
{
    /**
     * @var CronCollectionFactory
     */
    private $cronCollectionFactory;

    /**
     * @var AbandonedFactory
     */
    private $abandonedFactory;

    /**
     * @var Profiles
     */
    private $profileSyncModel;

    /**
     * @var Events
     */
    private $eventsSyncModel;

    /**
     * Cron constructor.
     *
     * @param CronCollectionFactory $cronCollectionFactory
     * @param AbandonedFactory $abandonedFactory
     * @param Profiles $profiles
     * @param Events $events
     */
    public function __construct(
        CronCollectionFactory $cronCollectionFactory,
        AbandonedFactory $abandonedFactory,
        Profiles $profiles,
        Events $events
    ) {
        $this->eventsSyncModel = $events;
        $this->profileSyncModel = $profiles;
        $this->abandonedFactory = $abandonedFactory;
        $this->cronCollectionFactory = $cronCollectionFactory;
    }

    /**
     * Sync events
     */
    public function syncEvents()
    {
        if ($this->hasJobAlreadyRun('apsis_one_sync_events')) {
            return;
        }

        $this->eventsSyncModel->sync();
    }

    /**
     * Sync profiles
     */
    public function syncProfiles()
    {
        if ($this->hasJobAlreadyRun('apsis_one_sync_profiles')) {
            return;
        }

        $this->profileSyncModel->batchAndSyncProfiles();
    }

    /**
     * Find abandoned carts
     */
    public function findAbandonedCarts()
    {
        if ($this->hasJobAlreadyRun('apsis_one_find_abandoned_carts')) {
            return;
        }

        $this->abandonedFactory
            ->create()
            ->processAbandonedCarts();
    }

    /**
     * @param string $jobCode
     * @return bool
     */
    private function hasJobAlreadyRun($jobCode)
    {
        $currentRunningJob = $this->cronCollectionFactory
            ->create()
            ->addFieldToFilter('job_code', $jobCode)
            ->addFieldToFilter('status', 'running')
            ->setPageSize(1);

        if ($currentRunningJob->getSize()) {
            $jobAlreadyExecuted =  $this->cronCollectionFactory
                ->create()
                ->addFieldToFilter('job_code', $jobCode)
                ->addFieldToFilter('scheduled_at', $currentRunningJob->getFirstItem()->getScheduledAt())
                ->addFieldToFilter('status', ['in' => ['success', 'failed']]);
            return ($jobAlreadyExecuted->getSize()) ? true : false;
        }
        return false;
    }
}
