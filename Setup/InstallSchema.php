<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Event;
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
        /** Profile */
        $this->createApsisProfileTable($installer);
        /** Profile Batch*/
        $this->createApsisProfileBatchTable($installer);
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
                'cart_data',
                Table::TYPE_BLOB,
                null,
                ['nullable' => false],
                'Cart Data'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                10,
                ['unsigned' => true, 'nullable' => false],
                'Store Id'
            )
            ->addColumn(
                'profile_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => false],
                'Profile Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => false],
                'Customer ID'
            )
            ->addColumn(
                'customer_email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Customer Email'
            )
            ->addColumn(
                'token',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'AC Token'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Created At'
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
            ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
            ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
            ->addIndex($installer->getIdxName($tableName, ['customer_email']), ['customer_email'])
            ->addIndex($installer->getIdxName($tableName, ['created_at']), ['created_at']);
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
                $installer->getTable('store'),
                'store_id'
            ),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        )->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_ABANDONED_TABLE,
                'customer_id',
                $installer->getTable('customer_entity'),
                'entity_id'
            ),
            'customer_id',
            $installer->getTable('customer_entity'),
            'entity_id',
            Table::ACTION_CASCADE
        )->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_ABANDONED_TABLE,
                'quote_id',
                $installer->getTable('quote'),
                'entity_id'
            ),
            'quote_id',
            $installer->getTable('quote'),
            'entity_id',
            Table::ACTION_CASCADE
        )->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_ABANDONED_TABLE,
                'profile_id',
                $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                'id'
            ),
            'profile_id',
            $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
            'id',
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
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Event Type'
            )
            ->addColumn(
                'event_data',
                Table::TYPE_BLOB,
                null,
                ['nullable' => false],
                'Event JSON Data'
            )
            ->addColumn(
                'sub_event_data',
                Table::TYPE_BLOB,
                null,
                ['nullable' => false],
                'Sub Event JSON Data'
            )
            ->addColumn(
                'profile_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => false],
                'Profile Id'
            )
            ->addColumn(
                'subscriber_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Subscriber Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Customer Id'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                5,
                ['unsigned' => true, 'nullable' => false],
                'Store ID'
            )
            ->addColumn(
                'email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Email'
            )
            ->addColumn(
                'status',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Status'
            )
            ->addColumn(
                'error_message',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
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
            ->addIndex($installer->getIdxName($tableName, ['profile_id']), ['profile_id'])
            ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
            ->addIndex($installer->getIdxName($tableName, ['subscriber_id']), ['subscriber_id'])
            ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
            ->addIndex($installer->getIdxName($tableName, ['event_type']), ['event_type'])
            ->addIndex($installer->getIdxName($tableName, ['status']), ['status'])
            ->addIndex($installer->getIdxName($tableName, ['email']), ['email'])
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
                $installer->getTable('store'),
                'store_id'
            ),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        )->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_EVENT_TABLE,
                'profile_id',
                $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                'id'
            ),
            'profile_id',
            $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
            'id',
            Table::ACTION_CASCADE
        );
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws Zend_Db_Exception
     */
    private function createApsisProfileBatchTable(SchemaSetupInterface $installer)
    {
        $this->dropTableIfExists($installer, ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE);

        $table = $installer->getConnection()->newTable(ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE);
        $table = $this->addColumnsToApsisProfileBatchTable($table);
        $table = $this->addIndexesToApsisProfileBatchTable($installer, $table);
        $table = $this->addForeignKeysToProfileBatchTable($installer, $table);

        $table->setComment('Apsis Profile Batch');
        $installer->getConnection()->createTable($table);
    }

    /**
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addColumnsToApsisProfileBatchTable(Table $table)
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
                'store_id',
                Table::TYPE_SMALLINT,
                5,
                ['unsigned' => true, 'nullable' => false],
                'Store ID'
            )
            ->addColumn(
                'file_path',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'File Path'
            )
            ->addColumn(
                'batch_type',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false],
                'Batch Type'
            )
            ->addColumn(
                'entity_ids',
                Table::TYPE_BLOB,
                null,
                ['nullable' => false],
                'Entity Ids'
            )
            ->addColumn(
                'sync_status',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Sync Status'
            )
            ->addColumn(
                'error_message',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
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
    private function addIndexesToApsisProfileBatchTable(SchemaSetupInterface $installer, Table $table)
    {
        $tableName = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE);
        $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
            ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
            ->addIndex($installer->getIdxName($tableName, ['sync_status']), ['sync_status'])
            ->addIndex($installer->getIdxName($tableName, ['batch_type']), ['batch_type'])
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
    private function addForeignKeysToProfileBatchTable(SchemaSetupInterface $installer, Table $table)
    {
        return $table->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE,
                'store_id',
                $installer->getTable('store'),
                'store_id'
            ),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        );
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws Zend_Db_Exception
     */
    private function createApsisProfileTable(SchemaSetupInterface $installer)
    {
        $this->dropTableIfExists($installer, ApsisCoreHelper::APSIS_PROFILE_TABLE);

        $table = $installer->getConnection()->newTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
        $table = $this->addColumnsToApsisProfileTable($table);
        $table = $this->addIndexesToApsisProfileTable($installer, $table);
        $table = $this->addForeignKeysToProfileTable($installer, $table);

        $table->setComment('Apsis Profiles');
        $installer->getConnection()->createTable($table);
    }

    /**
     * @param Table $table
     *
     * @return Table
     *
     * @throws Zend_Db_Exception
     */
    private function addColumnsToApsisProfileTable(Table $table)
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
                'integration_uid',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Integration User Id'
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
                ['unsigned' => true, 'nullable' => false],
                'Store ID'
            )
            ->addColumn(
                'subscriber_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Subscriber Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Customer Id'
            )
            ->addColumn(
                'email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Email'
            )
            ->addColumn(
                'subscriber_sync_status',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Subscriber Sync Status'
            )
            ->addColumn(
                'customer_sync_status',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Customer Sync Status'
            )
            ->addColumn(
                'is_subscriber',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Is Subscriber'
            )
            ->addColumn(
                'is_customer',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Is Customer'
            )
            ->addColumn(
                'error_message',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
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
    private function addIndexesToApsisProfileTable(SchemaSetupInterface $installer, Table $table)
    {
        $tableName = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
        $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
            ->addIndex($installer->getIdxName($tableName, ['subscriber_status']), ['subscriber_status'])
            ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
            ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
            ->addIndex($installer->getIdxName($tableName, ['subscriber_id']), ['subscriber_id'])
            ->addIndex($installer->getIdxName($tableName, ['subscriber_sync_status']), ['subscriber_sync_status'])
            ->addIndex($installer->getIdxName($tableName, ['customer_sync_status']), ['customer_sync_status'])
            ->addIndex($installer->getIdxName($tableName, ['is_subscriber']), ['is_subscriber'])
            ->addIndex($installer->getIdxName($tableName, ['is_customer']), ['is_customer'])
            ->addIndex($installer->getIdxName($tableName, ['email']), ['email'])
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
    private function addForeignKeysToProfileTable(SchemaSetupInterface $installer, Table $table)
    {
        return $table->addForeignKey(
            $installer->getFkName(
                ApsisCoreHelper::APSIS_PROFILE_TABLE,
                'store_id',
                $installer->getTable('store'),
                'store_id'
            ),
            'store_id',
            $installer->getTable('store'),
            'store_id',
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
