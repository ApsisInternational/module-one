<?php

namespace Apsis\One\Setup;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Newsletter\Model\Subscriber;
use Zend_Db_Expr;

class InstallData implements InstallDataInterface
{
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
        $this->fetchAndPopulateCustomers($installer);
        $this->fetchAndPopulateSubscribers($installer);
        $this->updateCustomerProfiles($installer);
    }

    /**
     * @param ModuleDataSetupInterface $installer
     */
    private function fetchAndPopulateCustomers(ModuleDataSetupInterface $installer)
    {
        $select = $installer->getConnection()->select()
            ->from(
                [
                    'customer' => $installer->getTable('customer_entity')
                ],
                [
                    'customer_id' => 'entity_id',
                    'email',
                    'is_customer' => new Zend_Db_Expr('1'),
                    'store_id'
                ]
            );
        $sqlQuery = $select->insertFromSelect(
            $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
            ['customer_id', 'email', 'is_customer', 'store_id'],
            false
        );
        $installer->getConnection()->query($sqlQuery);
    }

    /**
     * @param ModuleDataSetupInterface $installer
     */
    private function fetchAndPopulateSubscribers(ModuleDataSetupInterface $installer)
    {
        $select = $installer->getConnection()->select()
            ->from(
                [
                    'subscriber' => $installer->getTable(
                        'newsletter_subscriber'
                    )
                ],
                [
                    'subscriber_id',
                    'store_id',
                    'email' => 'subscriber_email',
                    'subscriber_status',
                    'is_subscriber' => new Zend_Db_Expr('1'),
                ]
            )
            ->where('subscriber_status = ?', Subscriber::STATUS_SUBSCRIBED)
            ->where('customer_id = ?', 0);

        $sqlQuery = $select->insertFromSelect(
            $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
            ['subscriber_id', 'store_id', 'email', 'subscriber_status', 'is_subscriber'],
            false
        );
        $installer->getConnection()->query($sqlQuery);
    }

    /**
     * @param ModuleDataSetupInterface $installer
     */
    private function updateCustomerProfiles(ModuleDataSetupInterface $installer)
    {
        $select = $installer->getConnection()->select();
        $select->from(
            [
                'subscriber' => $installer->getTable(
                    'newsletter_subscriber'
                )
            ],
            [
                'subscriber_id',
                'subscriber_status',
                'is_subscriber' => new Zend_Db_Expr('1'),
            ]
        )
            ->where('subscriber.subscriber_status = ?', Subscriber::STATUS_SUBSCRIBED)
            ->where('subscriber.customer_id = profile.customer_id');

        $sqlQuery = $select->crossUpdateFromSelect(
            ['profile' => $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE)]
        );
        $installer->getConnection()->query($sqlQuery);
    }
}
