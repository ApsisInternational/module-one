<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

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
        $this->logHelper->log(__METHOD__);

        $installer = $setup;
        $installer->startSetup();

        /** Remove old config */
        $this->profileResource->deleteAllModuleConfig($this->logHelper);

        /** Populate apsis profile table */
        $this->populateApsisProfileTable($installer);

        $installer->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $installer
     */
    private function populateApsisProfileTable(ModuleDataSetupInterface $installer)
    {
        $this->logHelper->log(__METHOD__);

        $apsisProfileTable = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
        $installer->getConnection()->truncateTable($apsisProfileTable);
        $magentoSubscriberTable = $installer->getTable('newsletter_subscriber');

        //Fetch customers to profile table
        $this->profileResource->fetchAndPopulateCustomers(
            $installer->getConnection(),
            $installer->getTable('customer_entity'),
            $apsisProfileTable,
            $this->logHelper
        );

        //Fetch subscribers to profile table
        $this->profileResource->fetchAndPopulateSubscribers(
            $installer->getConnection(),
            $magentoSubscriberTable,
            $apsisProfileTable,
            $this->logHelper
        );

        //Update customers with profile id in profile table
        $this->profileResource->updateCustomerProfiles(
            $installer->getConnection(),
            $magentoSubscriberTable,
            $apsisProfileTable,
            $this->logHelper
        );
    }
}
