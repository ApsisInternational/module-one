<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Throwable;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $logHelper;

    /**
     * @var InstallSchema
     */
    private InstallSchema $installSchema;

    /**
     * UpgradeSchema constructor.
     *
     * @param ApsisLogHelper $logHelper
     * @param InstallSchema $installSchema
     */
    public function __construct(ApsisLogHelper $logHelper, InstallSchema $installSchema)
    {
        $this->logHelper = $logHelper;
        $this->installSchema = $installSchema;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        try {
            $this->logHelper->log(__METHOD__);
            $setup->startSetup();
            if ($context->getVersion() && version_compare($context->getVersion(), '3.0.0', '<')) {
                $this->logHelper->log('v3.0.0, uninstalling and starting fresh install.');
                $this->installSchema->install($setup, $context);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }

        $setup->endSetup();
    }
}
