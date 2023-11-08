<?php

namespace Apsis\One\Service;

use Apsis\One\Logger\Logger;
use Magento\Cron\Model\ResourceModel\Schedule\Collection as CronCollection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronCollectionFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

abstract class AbstractCronService extends BaseService
{
    /**
     * @var CronCollectionFactory
     */
    private CronCollectionFactory $cronCollectionFactory;

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param CronCollectionFactory $cronCollectionFactory
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        CronCollectionFactory $cronCollectionFactory,
        ModuleListInterface $moduleList
    ) {
        parent::__construct($logger, $storeManager, $writer, $moduleList);
        $this->cronCollectionFactory = $cronCollectionFactory;
    }

    /**
     * @return string
     */
    abstract protected function getEntityCronJobCode(): string;

    /**
     * @param StoreInterface $store
     *
     * @return void
     */
    abstract protected function runEntityCronjobTaskByStore(StoreInterface $store): void;

    /**
     * @return CronCollection
     */
    private function getCronCollection(): CronCollection
    {
        return $this->cronCollectionFactory->create();
    }

    /**
     * @return void
     */
    public function runCronjobTask(): void
    {
        try {
            $cronjobCode = $this->getEntityCronJobCode();
            if (empty($cronjobCode) || $this->checkIfJobAlreadyRanForEntity($this->getEntityCronJobCode())) {
                return;
            }

            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage();
            foreach ($this->getStores() as $store) {
                $this->runEntityCronjobTaskByStore($store);
            }
            $this->logPerformanceData($cronjobCode, $startTime, $startMemory);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $jobCode
     *
     * @return bool
     */
    private function checkIfJobAlreadyRanForEntity(string $jobCode): bool
    {
        try {
            $collection = $this->getCronCollection()
                ->addFieldToFilter('job_code', $jobCode)
                ->addFieldToFilter('status', 'running')
                ->setPageSize(1);

            if (! $collection->getSize() || empty($dateTime = $collection->getFirstItem()->getScheduledAt())) {
                return false;
            }

            $jobAlreadyExecuted =  $this->getCronCollection()
                ->addFieldToFilter('scheduled_at', $dateTime)
                ->addFieldToFilter('job_code', $jobCode)
                ->addFieldToFilter('status', ['in' => ['success', 'failed']]);

            $check = (boolean) ($jobAlreadyExecuted->getSize());
            if ($check) {
                $this->debug(__METHOD__, ['Job already ran.' => $jobCode]);
            }

            return $check;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }
}
