<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Throwable;

class InstallData implements InstallDataInterface
{
    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var ApsisLogHelper
     */
    private $logHelper;

    /**
     * InstallData constructor.
     *
     * @param ProfileResource $profileResource
     * @param ApsisLogHelper $logHelper
     */
    public function __construct(ProfileResource $profileResource, ApsisLogHelper $logHelper)
    {
        $this->profileResource = $profileResource;
        $this->logHelper = $logHelper;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->logHelper->log(__METHOD__);
            $setup->startSetup();

            /** Remove old config */
            $configStatus = $this->profileResource->deleteAllModuleConfig($this->logHelper);
            if ($configStatus) {
                $this->logHelper->log('All configs are deleted, if existed.');
            } else {
                $this->logHelper->log('Unable to delete some configurations.');
            }

            /** Populate apsis profile table */
            $this->populateApsisProfileTable($setup);
        } catch (Throwable $e) {
            $setup->endSetup();
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $installer
     */
    private function populateApsisProfileTable(ModuleDataSetupInterface $installer)
    {
        try {
            $this->logHelper->log(__METHOD__);
            $apsisProfileTable = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
            $installer->getConnection()->truncateTable($apsisProfileTable);
            $this->logHelper->log("Table $apsisProfileTable truncated, if existed.");

            //Populate table with Customers and Subscribers
            $populateStatus = $this->profileResource->populateProfilesTable($this->logHelper);
            if ($populateStatus) {
                $this->logHelper->log('Profile table is populated with customer and subscribers.');
            } else {
                $this->logHelper->log('Unable to complete populate Profile table action.');
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }
}
