<?php

namespace Apsis\One\Service;

use Apsis\One\Logger\Logger;
use Apsis\One\Model\ResourceModel\ProfileResource;
use Apsis\One\Service\Data\HistoricalEvents;
use Apsis\One\Setup\InstallSchema;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class DeveloperService extends BaseService
{
    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * @var HistoricalEvents
     */
    private HistoricalEvents $historicalEvents;

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param ModuleListInterface $moduleList
     * @param ProfileResource $profileResource
     * @param HistoricalEvents $historicalEvents
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        ModuleListInterface $moduleList,
        ProfileResource $profileResource,
        HistoricalEvents $historicalEvents
    ) {
        parent::__construct($logger, $storeManager, $writer, $moduleList);
        $this->historicalEvents = $historicalEvents;
        $this->profileResource = $profileResource;
    }

    /**
     * @return bool
     */
    public function resetModule(): bool
    {
        try {
            $this->profileResource->truncateTable(array_keys(InstallSchema::TABLES), $this);
            $this->profileResource->deleteModuleConfigs($this);
            $this->profileResource->populateProfilesTable($this);
            $this->historicalEvents->identifyAndFetchHistoricalEvents($this);
            $this->debug(__METHOD__, ['Reset request from module config RESET button is performed.']);
            return true;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }
}
