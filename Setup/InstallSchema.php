<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\DB\Ddl\Table;
use Throwable;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $logHelper;

    /**
     * InstallSchema constructor.
     *
     * @param ApsisLogHelper $logHelper
     */
    public function __construct(ApsisLogHelper $logHelper)
    {
        $this->logHelper = $logHelper;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->logHelper->log(__METHOD__);

            $setup->startSetup();

            // Create Profile table
            $this->createProfileTable($setup);

            // Create Event table
            $this->createEventTable($setup);

            // Create AC table
            $this->createAbandonedTable($setup);

            // Create Queue table
            $this->createQueueTable($setup);

            // Create Webhook table
            $this->createWebhookTable($setup);
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @return void
     */
    private function createWebhookTable(SchemaSetupInterface $installer) : void
    {
        try {
            $this->logHelper->log(__METHOD__);

            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_WEBHOOK_TABLE);
            $this->dropTableIfExists($installer, $tableName);
            $table = $installer->getConnection()->newTable($tableName);

            if ($table) {
                $table = $this->addColumnsToWebhookTable($table);
            }
            if ($table) {
                $table = $this->addIndexesToWebhookTable($installer, $table);
            }
            if ($table) {
                $table = $this->addForeignKeysToWebhookTable($installer, $table);
            }

            if ($table) {
                $table->setComment('Apsis Webhooks');
                $installer->getConnection()->createTable($table);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Table $table
     *
     * @return Table|false
     */
    private function addColumnsToWebhookTable(Table $table)
    {
        try {
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
                'Store Id'
            )
            ->addColumn(
                'subscription_id',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Subscription Id'
            )
            ->addColumn(
                'type',
                Table::TYPE_SMALLINT,
                5,
                ['unsigned' => true, 'nullable' => false],
                'Webhook Type'
            )
            ->addColumn(
                'callback_url',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Callback Url'
            )
            ->addColumn(
                'fields',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Fields'
            )
            ->addColumn(
                'secret',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Secret'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Created At'
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addIndexesToWebhookTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_QUEUE_TABLE);
            return $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
                ->addIndex($installer->getIdxName($tableName, ['subscription_id']), ['subscription_id'])
                ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
                ->addIndex($installer->getIdxName($tableName, ['type']), ['type'])
                ->addIndex($installer->getIdxName($tableName, ['created_at']), ['created_at']);
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addForeignKeysToWebhookTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            return $table->addForeignKey(
                $installer->getFkName(
                    $installer->getTable(ApsisCoreHelper::APSIS_WEBHOOK_TABLE),
                    'store_id',
                    $installer->getTable('store'),
                    'store_id'
                ),
                'store_id',
                $installer->getTable('store'),
                'store_id',
                Table::ACTION_CASCADE
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @return void
     */
    private function createQueueTable(SchemaSetupInterface $installer) : void
    {
        try {
            $this->logHelper->log(__METHOD__);

            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_QUEUE_TABLE);
            $this->dropTableIfExists($installer, $tableName);
            $table = $installer->getConnection()->newTable($tableName);

            if ($table) {
                $table = $this->addColumnsToQueueTable($table);
            }
            if ($table) {
                $table = $this->addIndexesToQueueTable($installer, $table);
            }
            if ($table) {
                $table = $this->addForeignKeysToQueueTable($installer, $table);
            }

            if ($table) {
                $table->setComment('Apsis Queue');
                $installer->getConnection()->createTable($table);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Table $table
     *
     * @return Table|false
     */
    private function addColumnsToQueueTable(Table $table)
    {
        try {
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
                'Store Id'
            )
            ->addColumn(
                'profile_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => false],
                'Profile Id'
            )
            ->addColumn(
                'type',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false],
                'Type'
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
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Created At'
            )
            ->addColumn(
                'processed_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Processed At'
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addIndexesToQueueTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_QUEUE_TABLE);
            return $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
                ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
                ->addIndex($installer->getIdxName($tableName, ['profile_id']), ['profile_id'])
                ->addIndex($installer->getIdxName($tableName, ['sync_status']), ['sync_status'])
                ->addIndex($installer->getIdxName($tableName, ['created_at']), ['created_at'])
                ->addIndex($installer->getIdxName($tableName, ['processed_at']), ['processed_at']);
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addForeignKeysToQueueTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            return $table->addForeignKey(
                $installer->getFkName(
                    $installer->getTable(ApsisCoreHelper::APSIS_QUEUE_TABLE),
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
                    $installer->getTable(ApsisCoreHelper::APSIS_QUEUE_TABLE),
                    'profile_id',
                    $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                    'id'
                ),
                'profile_id',
                $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                'id',
                Table::ACTION_CASCADE
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @return void
     */
    private function createAbandonedTable(SchemaSetupInterface $installer): void
    {
        try {
            $this->logHelper->log(__METHOD__);

            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE);
            $this->dropTableIfExists($installer, $tableName);
            $table = $installer->getConnection()->newTable($tableName);

            if ($table) {
                $table = $this->addColumnsToAbandonedTable($table);
            }
            if ($table) {
                $table = $this->addIndexesToAbandonedTable($installer, $table);
            }
            if ($table) {
                $table = $this->addForeignKeysToAbandonedTable($installer, $table);
            }

            if ($table) {
                $table->setComment('Apsis Abandoned Carts');
                $installer->getConnection()->createTable($table);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Table $table
     *
     * @return Table|false
     */
    private function addColumnsToAbandonedTable(Table $table)
    {
        try {
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
                'quote_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => false],
                'Quote Id'
            )
            ->addColumn(
                'cart_data',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Cart Data'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                5,
                ['unsigned' => true, 'nullable' => false],
                'Store Id'
            )
            ->addColumn(
                'profile_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => false],
                'Profile Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Customer ID'
            )
            ->addColumn(
                'subscriber_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Customer ID'
            )
            ->addColumn(
                'email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Email'
            )
            ->addColumn(
                'token',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Abandoned Cart Token'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Created At'
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addIndexesToAbandonedTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE);
            return $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
                ->addIndex($installer->getIdxName($tableName, ['quote_id']), ['quote_id'])
                ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
                ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
                ->addIndex($installer->getIdxName($tableName, ['subscriber_id']), ['subscriber_id'])
                ->addIndex($installer->getIdxName($tableName, ['profile_id']), ['profile_id'])
                ->addIndex($installer->getIdxName($tableName, ['email']), ['email'])
                ->addIndex($installer->getIdxName($tableName, ['token']), ['token'])
                ->addIndex($installer->getIdxName($tableName, ['created_at']), ['created_at']);
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addForeignKeysToAbandonedTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            return $table->addForeignKey(
                $installer->getFkName(
                    $installer->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE),
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
                    $installer->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE),
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
                    $installer->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE),
                    'profile_id',
                    $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                    'id'
                ),
                'profile_id',
                $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                'id',
                Table::ACTION_CASCADE
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @return void
     */
    private function createEventTable(SchemaSetupInterface $installer): void
    {
        try {
            $this->logHelper->log(__METHOD__);

            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_EVENT_TABLE);
            $this->dropTableIfExists($installer, $tableName);
            $table = $installer->getConnection()->newTable($tableName);

            if ($table) {
                $table = $this->addColumnsToEventTable($table);
            }
            if ($table) {
                $table = $this->addIndexesToEventTable($installer, $table);
            }
            if ($table) {
                $table = $this->addForeignKeysToEventTable($installer, $table);
            }
            if ($table) {
                $table->setComment('Apsis Events');
                $installer->getConnection()->createTable($table);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Table $table
     *
     * @return Table|false
     */
    private function addColumnsToEventTable(Table $table)
    {
        try {
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
                Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Event JSON Data'
            )
            ->addColumn(
                'sub_event_data',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Sub Event JSON Data'
            )
            ->addColumn(
                'profile_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => false],
                'Profile Id'
            )
            ->addColumn(
                'subscriber_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Subscriber Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                10,
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
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addIndexesToEventTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_EVENT_TABLE);
            return $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
                ->addIndex($installer->getIdxName($tableName, ['profile_id']), ['profile_id'])
                ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
                ->addIndex($installer->getIdxName($tableName, ['subscriber_id']), ['subscriber_id'])
                ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
                ->addIndex($installer->getIdxName($tableName, ['event_type']), ['event_type'])
                ->addIndex($installer->getIdxName($tableName, ['sync_status']), ['sync_status'])
                ->addIndex($installer->getIdxName($tableName, ['email']), ['email'])
                ->addIndex($installer->getIdxName($tableName, ['created_at']), ['created_at'])
                ->addIndex($installer->getIdxName($tableName, ['updated_at']), ['updated_at']);
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addForeignKeysToEventTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            return $table->addForeignKey(
                $installer->getFkName(
                    $installer->getTable(ApsisCoreHelper::APSIS_EVENT_TABLE),
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
                    $installer->getTable(ApsisCoreHelper::APSIS_EVENT_TABLE),
                    'profile_id',
                    $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                    'id'
                ),
                'profile_id',
                $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                'id',
                Table::ACTION_CASCADE
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @return void
     */
    private function createProfileTable(SchemaSetupInterface $installer): void
    {
        try {
            $this->logHelper->log(__METHOD__);

            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
            $this->dropTableIfExists($installer, $tableName);
            $table = $installer->getConnection()->newTable($tableName);

            if ($table) {
                $table = $this->addColumnsToProfileTable($table);
            }
            if ($table) {
                $table = $this->addIndexesToProfileTable($installer, $table);
            }
            if ($table) {
                $table = $this->addForeignKeysToProfileTable($installer, $table);
            }
            if ($table) {
                $table->setComment('Apsis Profiles');
                $installer->getConnection()->createTable($table);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Table $table
     *
     * @return Table|false
     */
    private function addColumnsToProfileTable(Table $table)
    {
        try {
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
                'customer_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Customer Id'
            )
            ->addColumn(
                'group_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Customer Id'
            )
            ->addColumn(
                'subscriber_id',
                Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Subscriber Id'
            )
            ->addColumn(
                'email',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Email'
            )
            ->addColumn(
                'is_customer',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Is Customer?'
            )
            ->addColumn(
                'is_subscriber',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '0'],
                'Is Subscriber?'
            )
            ->addColumn(
                'subscriber_status',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => true, 'default' => null],
                'Subscriber Status'
            )
            ->addColumn(
                'profile_data',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Profile JSON Data'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Last Update Time'
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addIndexesToProfileTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            $tableName = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
            return $table->addIndex($installer->getIdxName($tableName, ['id']), ['id'])
                ->addIndex($installer->getIdxName($tableName, ['customer_id']), ['customer_id'])
                ->addIndex($installer->getIdxName($tableName, ['group_id']), ['group_id'])
                ->addIndex($installer->getIdxName($tableName, ['store_id']), ['store_id'])
                ->addIndex($installer->getIdxName($tableName, ['subscriber_id']), ['subscriber_id'])
                ->addIndex($installer->getIdxName($tableName, ['is_subscriber']), ['is_subscriber'])
                ->addIndex($installer->getIdxName($tableName, ['is_customer']), ['is_customer'])
                ->addIndex($installer->getIdxName($tableName, ['subscriber_status']), ['subscriber_status'])
                ->addIndex($installer->getIdxName($tableName, ['email']), ['email'])
                ->addIndex($installer->getIdxName($tableName, ['updated_at']), ['updated_at']);
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param Table $table
     *
     * @return Table|false
     */
    private function addForeignKeysToProfileTable(SchemaSetupInterface $installer, Table $table)
    {
        try {
            return $table->addForeignKey(
                $installer->getFkName(
                    $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                    'store_id',
                    $installer->getTable('store'),
                    'store_id'
                ),
                'store_id',
                $installer->getTable('store'),
                'store_id',
                Table::ACTION_CASCADE
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @param string $tableName
     *
     * @return void
     */
    public function dropTableIfExists(SchemaSetupInterface $installer, string $tableName): void
    {
        try {
            $tableName = $installer->getTable($tableName);
            if ($installer->getConnection()->isTableExists($tableName)) {
                $installer->getConnection()->dropTable($tableName);
            }
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }
}
