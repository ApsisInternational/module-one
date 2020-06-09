<?php

namespace Apsis\One\Setup;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;

class InstallData implements InstallDataInterface
{
    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * InstallData constructor.
     *
     * @param ProfileResource $profileResource
     */
    public function __construct(ProfileResource $profileResource)
    {
        $this->profileResource = $profileResource;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        /** Populate apsis profile table */
        $this->populateApsisProfileTable($installer);

        $installer->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $installer
     */
    private function populateApsisProfileTable(ModuleDataSetupInterface $installer)
    {
        $apsisProfileTable = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
        $magentoSubscriberTable = $installer->getTable('newsletter_subscriber');
        $this->profileResource->fetchAndPopulateCustomers(
            $installer->getConnection(),
            $installer->getTable('customer_entity'),
            $apsisProfileTable
        );
        $this->profileResource->fetchAndPopulateSubscribers(
                $installer->getConnection(),
                $magentoSubscriberTable,
                $apsisProfileTable
            );
        $this->profileResource->updateCustomerProfiles(
            $installer->getConnection(),
            $magentoSubscriberTable,
            $apsisProfileTable
        );
    }
}
