<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $setup->getConnection()->addColumn(
                $setup->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                'topic_subscription',
                [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'default' => null,
                    'comment' => 'Subscription to topics'
                ]
            );
        }
        $setup->endSetup();
    }
}
