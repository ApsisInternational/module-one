<?php

namespace Apsis\One\Setup;

use Apsis\One\Service\BaseService;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Throwable;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var InstallData
     */
    private InstallData $installData;

    /**
     * @param BaseService $baseService
     * @param InstallData $installData
     */
    public function __construct(BaseService $baseService, InstallData $installData)
    {
        $this->baseService = $baseService;
        $this->installData = $installData;
    }


    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context): void
    {
        $this->baseService->log(__METHOD__);
        $startTime = microtime(true);
        $startMemory = memory_get_peak_usage();

        try {
            $setup->startSetup();
            if ($context->getVersion() && version_compare($context->getVersion(), '3.0.0', '<')) {
                // v3.0.0, uninstalling and starting fresh install.
                $this->installData->install($setup, $context);
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
        $setup->endSetup();
        $this->baseService->logPerformanceData(__METHOD__, $startTime, $startMemory);
    }
}
