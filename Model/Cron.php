<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\Cron\CollectionFactory as CronCollectionFactory;
use Apsis\One\Model\Sync\Profiles;

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
     * Cron constructor.
     *
     * @param CronCollectionFactory $cronCollectionFactory
     * @param AbandonedFactory $abandonedFactory
     * @param Profiles $profiles
     */
    public function __construct(
        CronCollectionFactory $cronCollectionFactory,
        AbandonedFactory $abandonedFactory,
        Profiles $profiles
    ) {
        $this->profileSyncModel = $profiles;
        $this->abandonedFactory = $abandonedFactory;
        $this->cronCollectionFactory = $cronCollectionFactory;
    }

    public function syncEvents()
    {
        if ($this->hasJobAlreadyRun('apsis_one_sync_events')) {
            return;
        }

        //@todo run code
    }

    /**
     * Sync profiles
     */
    public function syncProfiles()
    {
        if ($this->hasJobAlreadyRun('apsis_one_sync_profiles')) {
            return;
        }

        $this->profileSyncModel->syncProfiles();
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
