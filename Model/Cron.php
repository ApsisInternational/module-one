<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\Cron\CollectionFactory as CronCollectionFactory;

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
     * Cron constructor.
     *
     * @param CronCollectionFactory $cronCollectionFactory
     * @param AbandonedFactory $abandonedFactory
     */
    public function __construct(
        CronCollectionFactory $cronCollectionFactory,
        AbandonedFactory $abandonedFactory
    ) {
        $this->abandonedFactory = $abandonedFactory;
        $this->cronCollectionFactory = $cronCollectionFactory;
    }

    public function syncEvents()
    {
        if ($this->hasJobAlreadyRun('apsis_sync_events')) {
            return;
        }

        //@todo run code
    }

    public function syncSubscribers()
    {
        if ($this->hasJobAlreadyRun('apsis_sync_subscribers')) {
            return;
        }

        //@todo run code
    }

    /**
     * Find abandoned carts
     */
    public function findAbandonedCarts()
    {
        if ($this->hasJobAlreadyRun('apsis_abandoned_carts')) {
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
