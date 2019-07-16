<?php

namespace Apsis\One\Setup;

use Apsis\One\Helper\Data as Helper;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;
        $installer->startSetup();

        $this->populateApsisSubscriberTable($installer);

        $installer->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $installer
     *
     * @return null
     */
    private function populateApsisSubscriberTable($installer)
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
            );

        $sqlQuery = $select->insertFromSelect(
            $installer->getTable(Helper::APSIS_SUBSCRIBER_TABLE),
            $insertArray,
            false
        );
        $installer->getConnection()->query($sqlQuery);
    }
}
