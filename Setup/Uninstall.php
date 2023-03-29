<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\SetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Throwable;

class Uninstall implements UninstallInterface
{
    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $logHelper;

    /**
     * Uninstall constructor.
     *
     * @param ApsisLogHelper $logHelper
     */
    public function __construct(ApsisLogHelper $logHelper)
    {
        $this->logHelper = $logHelper;
    }

    /**
     * @var array
     */
    protected array $apsisTablesArr = [
        ApsisCoreHelper::APSIS_ABANDONED_TABLE,
        ApsisCoreHelper::APSIS_EVENT_TABLE,
        ApsisCoreHelper::APSIS_PROFILE_TABLE
    ];

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->logHelper->log(__METHOD__);

            //Remove all module tables
            $this->removeAllModuleTables($setup);

            // Remove all module data from Magento tables
            $this->removeAllModuleDataFromMagentoTables($setup);
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SetupInterface $setup
     *
     * @return void
     */
    public function removeAllModuleTables(SetupInterface $setup): void
    {
        $this->logHelper->log(__METHOD__);

        foreach ($this->apsisTablesArr as $tableName) {
            $setup->getConnection()->dropTable($setup->getTable($tableName));
        }
    }

    /**
     * @param SetupInterface $setup
     *
     * @return void
     */
    public function removeAllModuleDataFromMagentoTables(SetupInterface $setup): void
    {
        $this->logHelper->log(__METHOD__);

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
    }
}
