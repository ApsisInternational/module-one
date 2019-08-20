<?php

namespace Apsis\One\Setup;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Newsletter\Model\Subscriber;

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

        /** Populate apsis subscriber table */
        $this->populateApsisSubscriberTable($installer);

        $installer->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $installer
     */
    private function populateApsisSubscriberTable(ModuleDataSetupInterface $installer)
    {
        $insertArray = [
            'subscriber_id',
            'store_id',
            'customer_id',
            'subscriber_email',
            'subscriber_status'
        ];
        $select = $installer->getConnection()->select()
            ->from(
                [
                    'subscriber' => $installer->getTable(
                        'newsletter_subscriber'
                    )
                ],
                $insertArray
            )->where('subscriber_status =?', Subscriber::STATUS_SUBSCRIBED);

        $sqlQuery = $select->insertFromSelect(
            $installer->getTable(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE),
            $insertArray,
            false
        );
        $installer->getConnection()->query($sqlQuery);
    }
}
