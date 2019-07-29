<?php

namespace Apsis\One\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\DB\Ddl\Table;
use Zend_Db_Exception;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @throws Zend_Db_Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        // Create entities tables
        /** Subscriber */
        $this->createApsisSubscriberTable($installer);
        /** Events */
        $this->createApsisEventTable($installer);
        /** Abandoned Carts */
        $this->createApsisAbandonedTable($installer);

        $installer->endSetup();
    }

    /**
     * @param SchemaSetupInterface $installer
     * @throws Zend_Db_Exception
     */
    private function createApsisAbandonedTable(SchemaSetupInterface $installer)
    {
        $this->dropTableIfExists($installer, ApsisCoreHelper::APSIS_ABANDONED_TABLE);

        $table = $installer->getConnection()->newTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE);
        $table = $this->addColumnsToApsisAbandonedTable($table);
        $table = $this->addIndexesToApsisAbandonedTable($installer, $table);
        $table = $this->addForeignKeysToAbandonedTable($installer, $table);

        $table->setComment('Apsis Abandoned Carts');
        $installer->getConnection()->createTable($table);
    }

    /**
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addColumnsToApsisAbandonedTable(Table $table)
    {
        return $table->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            [
                'primary' => true,
                'identity' => true,
                'unsigned' => true,
                'nullable' => false
            ],
            'Primary Key'
        )
            ->addColumn(
                'quote_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => true],
                'Quote Id'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                10,
                ['unsigned' => true, 'nullable' => true],
                'Store Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Customer ID'
            )
            ->addColumn(
                'customer_email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Customer Email'
            )
            ->addColumn(
                'status',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'AC Status'
            )
            ->addColumn(
                'is_active',
                Table::TYPE_SMALLINT,
                5,
                ['unsigned' => true, 'nullable' => false, 'default' => '1'],
                'Is Quote Active'
            )
            ->addColumn(
                'quote_updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Quote updated at'
            )
            ->addColumn(
                'abandoned_cart_number',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => 0],
                'Abandoned Cart number'
            )
            ->addColumn(
                'items_count',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => true, 'default' => 0],
                'Quote items count'
            )
            ->addColumn(
                'items_ids',
                Table::TYPE_TEXT,
                255,
                ['unsigned' => true, 'nullable' => true],
                'Quote item ids'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Created At'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Updated at'
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addIndexesToApsisAbandonedTable(SchemaSetupInterface $installer, Table $table)
    {
        $tableName = $installer->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE);
        $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
            ->addIndex($installer->getIdxName($tableName, ['quote_id']), ['quote_id'])
            ->addIndex($installer->getIdxName($tableName, ['status']), ['status'])
            ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
            ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
            ->addIndex($installer->getIdxName($tableName, ['customer_email']), ['customer_email'])
            ->addIndex($installer->getIdxName($tableName, ['created_at']), ['created_at'])
            ->addIndex($installer->getIdxName($tableName, ['updated_at']), ['updated_at']);
        return $table;
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addForeignKeysToAbandonedTable(SchemaSetupInterface $installer, Table $table)
    {
        return $table->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_ABANDONED_TABLE,
                'store_id',
                'store',
                'store_id'
            ),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        )
            ->addForeignKey(
                $installer->getFkName(
                    ApsisCoreHelper::APSIS_ABANDONED_TABLE,
                    'customer_id',
                    'customer_entity',
                    'entity_id'
                ),
                'customer_id',
                $installer->getTable('customer_entity'),
                'entity_id',
                Table::ACTION_CASCADE
            )
            ->addForeignKey(
                $installer->getFkName(
                    ApsisCoreHelper::APSIS_ABANDONED_TABLE,
                    'quote_id',
                    'quote',
                    'entity_id'
                ),
                'quote_id',
                $installer->getTable('quote'),
                'entity_id',
                Table::ACTION_CASCADE
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws Zend_Db_Exception
     */
    private function createApsisEventTable(SchemaSetupInterface $installer)
    {
        $this->dropTableIfExists($installer, ApsisCoreHelper::APSIS_EVENT_TABLE);

        $table = $installer->getConnection()->newTable(ApsisCoreHelper::APSIS_EVENT_TABLE);
        $table = $this->addColumnsToApsisEventTable($table);
        $table = $this->addIndexesToApsisEventTable($installer, $table);
        $table = $this->addForeignKeysToEventTable($installer, $table);

        $table->setComment('Apsis Events');
        $installer->getConnection()->createTable($table);
    }

    /**
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addColumnsToApsisEventTable(Table $table)
    {
        return $table->addColumn(
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
                'event_type',
                Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Event Type'
            )
            ->addColumn(
                'type_id',
                Table::TYPE_INTEGER,
                11,
                ['nullable' => true],
                'Type ID'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => false],
                'Customer Id'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                5,
                ['unsigned' => true, 'nullable' => false, 'default' => '0'],
                'Store ID'
            )
            ->addColumn(
                'customer_email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Subscriber Email'
            )
            ->addColumn(
                'status',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => true],
                'Is Registered'
            )
            ->addColumn(
                'error_message',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Error Message'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Creation Time'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Update Time'
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addIndexesToApsisEventTable(SchemaSetupInterface $installer, Table $table)
    {
        $tableName = $installer->getTable(ApsisCoreHelper::APSIS_EVENT_TABLE);
        $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
            ->addIndex($installer->getIdxName($tableName, ['type_id']), ['type_id'])
            ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
            ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
            ->addIndex($installer->getIdxName($tableName, ['event_type']), ['event_type'])
            ->addIndex($installer->getIdxName($tableName, ['status']), ['status'])
            ->addIndex($installer->getIdxName($tableName, ['customer_email']), ['customer_email'])
            ->addIndex($installer->getIdxName($tableName, ['created_at']), ['created_at'])
            ->addIndex($installer->getIdxName($tableName, ['updated_at']), ['updated_at']);
        return $table;
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addForeignKeysToEventTable(SchemaSetupInterface $installer, Table $table)
    {
        return $table->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_EVENT_TABLE,
                'store_id',
                'store',
                'store_id'
            ),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        )
            ->addForeignKey(
                $installer->getFkName(
                    ApsisCoreHelper::APSIS_EVENT_TABLE,
                    'customer_id',
                    'customer_entity',
                    'entity_id'
                ),
                'customer_id',
                $installer->getTable('customer_entity'),
                'entity_id',
                Table::ACTION_CASCADE
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws Zend_Db_Exception
     */
    private function createApsisSubscriberTable(SchemaSetupInterface $installer)
    {
        $this->dropTableIfExists($installer, ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE);

        $table = $installer->getConnection()->newTable(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE);
        $table = $this->addColumnsToApsisSubscriberTable($table);
        $table = $this->addIndexesToApsisSubscriberTable($installer, $table);
        $table = $this->addForeignKeysToSubscriberTable($installer, $table);

        $table->setComment('Apsis Subscribers');
        $installer->getConnection()->createTable($table);
    }

    /**
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addColumnsToApsisSubscriberTable(Table $table)
    {
        return $table->addColumn(
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
            )
            ->addColumn(
                'error_message',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Error Message'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Last Update Time'
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addIndexesToApsisSubscriberTable(SchemaSetupInterface $installer, Table $table)
    {
        $tableName = $installer->getTable(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE);
        $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
            ->addIndex($installer->getIdxName($tableName, ['subscriber_status']), ['subscriber_status'])
            ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
            ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
            ->addIndex($installer->getIdxName($tableName, ['subscriber_id']), ['subscriber_id'])
            ->addIndex($installer->getIdxName($tableName, ['imported']), ['imported'])
            ->addIndex($installer->getIdxName($tableName, ['subscriber_email']), ['subscriber_email'])
            ->addIndex($installer->getIdxName($tableName, ['suppressed']), ['suppressed'])
            ->addIndex($installer->getIdxName($tableName, ['updated_at']), ['updated_at']);
        return $table;
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addForeignKeysToSubscriberTable(SchemaSetupInterface $installer, Table $table)
    {
        return $table->addForeignKey(
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
        )
            ->addForeignKey(
                $installer->getFkName(
                    ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE,
                    'subscriber_id',
                    'newsletter_subscriber',
                    'subscriber_id'
                ),
                'subscriber_id',
                $installer->getTable('newsletter_subscriber'),
                'subscriber_id',
                Table::ACTION_CASCADE
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param string $tableName
     */
    private function dropTableIfExists(SchemaSetupInterface $installer, string $tableName)
    {
        $tableName = $installer->getTable($tableName);
        if ($installer->getConnection()->isTableExists($installer->getTable($tableName))) {
            $installer->getConnection()->dropTable(
                $installer->getTable($tableName)
            );
        }
    }
}
