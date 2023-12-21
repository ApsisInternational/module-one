<?php

namespace Apsis\One\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\SetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Apsis\One\Service\BaseService;
use Throwable;

class Uninstall implements UninstallInterface
{
    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @param BaseService $baseService
     */
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        try {
            $this->baseService->log(__METHOD__);

            //Remove all module tables
            $this->removeAllModuleTables($setup);

            // Remove all module data from Magento tables
            $this->removeAllModuleDataFromMagentoTables($setup);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SetupInterface $setup
     *
     * @return void
     */
    public function removeAllModuleTables(SetupInterface $setup): void
    {
        try {
            $this->baseService->log(__METHOD__);

            foreach (array_merge(array_keys(InstallSchema::TABLES), ['apsis_profile_batch']) as $tableName) {
                if ($setup->getConnection()->isTableExists($setup->getTable($tableName))) {
                    $setup->getConnection()->dropTable($setup->getTable($tableName));
                }
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SetupInterface $setup
     *
     * @return void
     */
    public function removeAllModuleDataFromMagentoTables(SetupInterface $setup): void
    {
        try {
            $this->baseService->log(__METHOD__);

            //Remove all module config
            $setup->getConnection()->delete(
                $setup->getTable('core_config_data'),
                "path like 'apsis_one%'"
            );

            //Remove rules belonging to APSIS role
            $select = $setup->getConnection()->select()
                ->from($setup->getTable('authorization_role'), 'role_id')
                ->where('role_name = ?', 'APSIS Support Agent');
            $role = $setup->getConnection()->fetchOne($select);
            if ($role) {
                $setup->getConnection()->delete(
                    $setup->getTable('authorization_rule'),
                    ['role_id = ?' => (int) $role]
                );
            }

            //Remove role created by the module
            $setup->getConnection()->delete(
                $setup->getTable('authorization_role'),
                ['role_name = ?' => 'APSIS Support Agent']
            );

            //Remove all ui bookmarks belonging to module to force rebuild new ui bookmarks
            $setup->getConnection()->delete(
                $setup->getTable('ui_bookmark'),
                $setup->getConnection()->quoteInto(
                    'namespace in (?)',
                    ['apsis_abandoned_grid', 'apsis_event_grid', 'apsis_profile_grid']
                )
            );

            //Removed all cron jobs.
            $setup->getConnection()->delete(
                $setup->getTable('cron_schedule'),
                "job_code like 'apsis_one%'"
            );
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
    }
}
