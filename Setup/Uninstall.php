<?php

namespace Apsis\One\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;

class Uninstall implements UninstallInterface
{
    /**
     * @var array
     */
    protected $apsisTablesArr = [
        ApsisCoreHelper::APSIS_ABANDONED_TABLE,
        ApsisCoreHelper::APSIS_EVENT_TABLE,
        ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE,
        ApsisCoreHelper::APSIS_PROFILE_TABLE
    ];

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        //Remove all module tables
        foreach ($this->apsisTablesArr as $tableName) {
            $setup->getConnection()->dropTable($setup->getTable($tableName));
        }

        //Remove all module config
        $setup->getConnection()->delete($setup->getTable('core_config_data'), "path like 'apsis_one%'");
    }
}
