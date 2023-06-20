<?php

namespace Apsis\One\Setup;

use Apsis\One\Service\BaseService;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Throwable;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var InstallSchema
     */
    private InstallSchema $installSchema;

    /**
     * @param BaseService $baseService
     * @param InstallSchema $installSchema
     */
    public function __construct(BaseService $baseService, InstallSchema $installSchema)
    {
        $this->baseService = $baseService;
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
            $this->baseService->log(__METHOD__);
            $setup->startSetup();
            if ($context->getVersion() && version_compare($context->getVersion(), '3.0.0', '<')) {
                $this->baseService->log('v3.0.0, uninstalling and starting fresh install.');
                $this->installSchema->install($setup, $context);
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
        $setup->endSetup();
    }
}
