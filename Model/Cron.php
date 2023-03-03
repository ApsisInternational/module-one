<?php

namespace Apsis\One\Model;

use Apsis\One\Model\Abandoned\Find;
use Apsis\One\Model\ResourceModel\Cron\CollectionFactory as CronCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Events;
use Throwable;

class Cron
{
    /**
     * @var CronCollectionFactory
     */
    private CronCollectionFactory $cronCollectionFactory;

    /**
     * @var Find
     */
    private Find $abandonedFind;

    /**
     * @var Events
     */
    private Events $eventsSyncModel;

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $coreHelper;

    /**
     * Cron constructor.
     *
     * @param CronCollectionFactory $cronCollectionFactory
     * @param Find $abandonedFind
     * @param Events $events
     * @param ApsisCoreHelper $coreHelper
     */
    public function __construct(
        CronCollectionFactory $cronCollectionFactory,
        Find $abandonedFind,
        Events $events,
        ApsisCoreHelper $coreHelper
    ) {
        $this->coreHelper = $coreHelper;
        $this->eventsSyncModel = $events;
        $this->abandonedFind = $abandonedFind;
        $this->cronCollectionFactory = $cronCollectionFactory;
    }

    /**
     * Sync events
     *
     * @return void
     */
    public function syncEvents(): void
    {
        try {
            if ($this->checkIfJobAlreadyRan('apsis_one_sync_events')) {
                return;
            }

            $this->eventsSyncModel->process($this->coreHelper);
        } catch (Throwable $e) {
            $this->coreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * Sync profiles
     *
     * @return void
     */
    public function syncProfiles(): void
    {
        try {
            if ($this->checkIfJobAlreadyRan('apsis_one_sync_profiles')) {
                return;
            }

            //@todo
        } catch (Throwable $e) {
            $this->coreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * Find abandoned carts
     *
     * @return void
     */
    public function findAbandonedCarts(): void
    {
        try {
            if ($this->checkIfJobAlreadyRan('apsis_one_find_abandoned_carts')) {
                return;
            }

            $this->abandonedFind->process($this->coreHelper);
        } catch (Throwable $e) {
            $this->coreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $jobCode
     *
     * @return bool
     */
    private function checkIfJobAlreadyRan(string $jobCode): bool
    {
        try {
            $currentRunningJob = $this->cronCollectionFactory
                ->create()
                ->addFieldToFilter('job_code', $jobCode)
                ->addFieldToFilter('status', 'running')
                ->setPageSize(1);

            if (! $currentRunningJob->getSize() ||
                empty($dateTime = $currentRunningJob->getFirstItem()->getScheduledAt())) {
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
        } catch (Throwable $e) {
            $this->coreHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
