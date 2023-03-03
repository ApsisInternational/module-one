<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Service\Log as logHelper;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Throwable;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var logHelper
     */
    private logHelper $logHelper;

    /**
     * @var InstallData
     */
    private InstallData $installData;

    /**
     * @param logHelper $logHelper
     * @param InstallData $installData
     */
    public function __construct(logHelper $logHelper, InstallData $installData)
    {
        $this->logHelper = $logHelper;
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
        try {
            $this->logHelper->log(__METHOD__);
            $setup->startSetup();
            if ($context->getVersion() && version_compare($context->getVersion(), '3.0.0', '<')) {
                // v3.0.0, uninstalling and starting fresh install.
                $this->installData->install($setup, $context);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }

        $setup->endSetup();
    }
}
