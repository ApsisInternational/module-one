<?php

namespace Apsis\One\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $this->createApsisSubscriberTable($installer);

        $installer->endSetup();
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @return null
     */
    private function createApsisSubscriberTable($installer)
    {
        $tableName = $installer->getTable(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE);
        $this->dropTableIfExists($installer, $tableName);

        $subscriberTable = $installer->getConnection()->newTable($tableName);
        $subscriberTable = $this->addColumnsToApsisSubscriberTable($subscriberTable);
        $subscriberTable = $this->addIndexesToApsisSubscriberTable($installer, $subscriberTable);

        $subscriberTable->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE,
                'store_id',
                'store',
                'store_id'
            ),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        );

        $subscriberTable->setComment('Apsis Subscribers');
        $installer->getConnection()->createTable($subscriberTable);
    }

    /**
     * @param Table $subscriberTable
     * @return Table
     */
    private function addColumnsToApsisSubscriberTable($subscriberTable)
    {
        return $subscriberTable->addColumn(
            'id',
            Table::TYPE_INTEGER,
            10,
            [
                'primary' => true,
                'identity' => true,
                'unsigned' => true,
                'nullable' => false
            ],
            'Primary Key'
        )
            ->addColumn(
                'subscriber_status',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => true],
                'Subscriber status'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                5,
                ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                'Store ID'
            )
            ->addColumn(
                'subscriber_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => false],
                'Subscriber Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => false],
                'Customer Id'
            )
            ->addColumn(
                'subscriber_email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Subscriber Email'
            )
            ->addColumn(
                'imported',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => true],
                'Is Imported'
            )
            ->addColumn(
                'suppressed',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => true],
                'Is Suppressed'
            );;
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $subscriberTable
     * @return Table
     */
    private function addIndexesToApsisSubscriberTable($installer, $subscriberTable)
    {
        return $subscriberTable->addIndex(
            $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['id']),
            ['id']
        )
            ->addIndex(
                $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['subscriber_status']),
                ['subscriber_status']
            )
            ->addIndex(
                $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['customer_id']),
                ['customer_id']
            )
            ->addIndex(
                $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['store_id']),
                ['store_id']
            )
            ->addIndex(
                $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['subscriber_id']),
                ['subscriber_id']
            )
            ->addIndex(
                $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['imported']),
                ['imported']
            )
            ->addIndex(
                $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['subscriber_email']),
                ['subscriber_email']
            )
            ->addIndex(
                $installer->getIdxName(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE, ['suppressed']),
                ['suppressed']
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param string $table
     */
    private function dropTableIfExists($installer, $table)
    {
        if ($installer->getConnection()->isTableExists($installer->getTable($table))) {
            $installer->getConnection()->dropTable(
                $installer->getTable($table)
            );
        }
    }
}