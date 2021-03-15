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
            $this->upgradeOneOneZero($setup);
        }
        if (version_compare($context->getVersion(), '1.3.0', '<')) {
            $this->upgradeOneThreeZero($setup);
        }
        if (version_compare($context->getVersion(), '1.8.0', '<')) {
            $this->upgradeOneEightZero($setup);
        }
        if (version_compare($context->getVersion(), '1.9.1', '<')) {
            $this->upgradeOneNineOne($setup);
        }
        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    private function upgradeOneNineOne(SchemaSetupInterface $setup)
    {
        $tableName = $setup->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE);
        $setup->getConnection()->addColumn(
            $tableName,
            'subscriber_id',
            [
                'type' => Table::TYPE_INTEGER,
                'nullable' => true,
                'default' => null,
                'comment' => 'Subscriber ID'
            ]
        );
        $setup->getConnection()->modifyColumn(
            $tableName,
            'customer_id',
            [
                'type' => Table::TYPE_INTEGER,
                'nullable' => true,
                'default' => null,
                'comment' => 'Customer ID'
            ]
        );
        $setup->getConnection()->addIndex(
            $tableName,
            $setup->getIdxName($tableName, ['subscriber_id']),
            ['subscriber_id']
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    private function upgradeOneEightZero(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->dropColumn(
            $setup->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
            'topic_subscription'
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    private function upgradeOneOneZero(SchemaSetupInterface $setup)
    {
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

    /**
     * @param SchemaSetupInterface $setup
     */
    private function upgradeOneThreeZero(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->dropForeignKey(
            $setup->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE),
            $setup->getFkName(
                ApsisCoreHelper::APSIS_ABANDONED_TABLE,
                'customer_id',
                $setup->getTable('customer_entity'),
                'entity_id'
            )
        );
    }
}
