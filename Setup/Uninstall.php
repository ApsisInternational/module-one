<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;

class Uninstall implements UninstallInterface
{
    /**
     * @var ApsisLogHelper
     */
    private $logHelper;

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
        $this->logHelper->log(__METHOD__);

        //Remove all module tables
        foreach ($this->apsisTablesArr as $tableName) {
            $setup->getConnection()->dropTable($setup->getTable($tableName));
        }

        //Remove all module config
        $setup->getConnection()->delete($setup->getTable('core_config_data'), "path like 'apsis_one%'");
    }
}
