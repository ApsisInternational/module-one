<?php

namespace Apsis\One\Setup;

use Apsis\One\Service\BaseService;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Throwable;

class InstallSchema implements InstallSchemaInterface
{
    const TABLES = [
        BaseService::APSIS_PROFILE_TABLE => [
            self::COMMON_COLUMNS,
            self::COMMON_COLUMNS_PROFILE_EVENT_AC,
            self::COLUMNS_PROFILE,
            self::COMMON_COLUMNS_PROFILE_EVENT_WEBHOOK_CONFIG
        ],
        BaseService::APSIS_EVENT_TABLE => [
            self::COMMON_COLUMNS,
            self::COLUMNS_EVENT,
            self::COMMON_COLUMNS_PROFILE_EVENT_WEBHOOK_CONFIG,
            self::COMMON_COLUMNS_PROFILE_EVENT_AC,
            self::COMMON_COLUMNS_EVENT_QUEUE,
            self::COMMON_COLUMNS_EVENT_QUEUE_AC_WEBHOOK
        ],
        BaseService::APSIS_ABANDONED_TABLE => [
            self::COMMON_COLUMNS,
            self::COLUMNS_AC,
            self::COMMON_COLUMNS_PROFILE_EVENT_AC,
            self::COMMON_COLUMNS_EVENT_QUEUE_AC_WEBHOOK
        ],
        BaseService::APSIS_WEBHOOK_TABLE => [
            self::COMMON_COLUMNS,
            self::COLUMNS_WEBHOOK,
            self::COMMON_COLUMNS_EVENT_QUEUE_AC_WEBHOOK,
            self::COMMON_COLUMNS_PROFILE_EVENT_WEBHOOK_CONFIG
        ],
        BaseService::APSIS_QUEUE_TABLE => [
            self::COMMON_COLUMNS,
            self::COLUMNS_QUEUE,
            self::COMMON_COLUMNS_EVENT_QUEUE_AC_WEBHOOK,
            self::COMMON_COLUMNS_EVENT_QUEUE
        ],
        BaseService::APSIS_CONFIG_TABLE => [
            self::COMMON_COLUMNS,
            self::COLUMNS_CONFIG,
            self::COMMON_COLUMNS_PROFILE_EVENT_WEBHOOK_CONFIG
        ]
    ];
    const COMMON_COLUMNS = [
        'id' => [
            'id',
            Table::TYPE_INTEGER,
            10,
            ['primary' => true, 'identity' => true, 'unsigned' => true, 'nullable' => false],
            'Primary Key'
        ],
        'store_id' => [
            'store_id',
            Table::TYPE_SMALLINT,
            5,
            ['unsigned' => true, 'nullable' => false],
            'Store Id'
        ]
    ];
    const COMMON_COLUMNS_PROFILE_EVENT_WEBHOOK_CONFIG = [
        'updated_at' => [
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            [],
            'Last Update Time'
        ]
    ];
    const COMMON_COLUMNS_EVENT_QUEUE_AC_WEBHOOK = [
        'created_at' => [
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            [],
            'Created At'
        ]
    ];
    const COMMON_COLUMNS_EVENT_QUEUE = [
        'profile_id' => [
            'profile_id',
            Table::TYPE_INTEGER,
            10,
            ['unsigned' => true, 'nullable' => false],
            'Profile Id'
        ],
        'sync_status' => [
            'sync_status',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '0'],
            'Sync Status'
        ],
        'type' => [
            'type',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => false],
            'Item Type'
        ],
        'error_message' => [
            'error_message',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Error Message'
        ]
    ];
    const COMMON_COLUMNS_PROFILE_EVENT_AC = [
        'customer_id' => [
            'customer_id',
            Table::TYPE_INTEGER,
            10,
            ['unsigned' => true, 'nullable' => true, 'default' => null],
            'Customer Id'
        ],
        'subscriber_id' => [
            'subscriber_id',
            Table::TYPE_INTEGER,
            10,
            ['unsigned' => true, 'nullable' => true, 'default' => null],
            'Subscriber Id'
        ],
        'email' => [
            'email',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Email'
        ]
    ];
    const COLUMNS_PROFILE = [
        'group_id' => [
            'group_id',
            Table::TYPE_INTEGER,
            10,
            ['unsigned' => true, 'nullable' => true, 'default' => null],
            'Group Id'
        ],
        'is_customer' => [
            'is_customer',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '0'],
            'Is Customer?'
        ],
        'is_subscriber' => [
            'is_subscriber',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '0'],
            'Is Subscriber?'
        ],
        'subscriber_status' => [
            'subscriber_status',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => true, 'default' => null],
            'Subscriber Status'
        ],
        'profile_data' => [
            'profile_data',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false, 'default' => ''],
            'Profile JSON Data'
        ]
    ];
    const COLUMNS_EVENT = [
        'event_data' => [
            'event_data',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Event JSON Data'
        ],
        'sub_event_data' => [
            'sub_event_data',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Sub Event JSON Data'
        ]
    ];
    const COLUMNS_AC = [
        'quote_id' => [
            'quote_id',
            Table::TYPE_INTEGER,
            10,
            ['unsigned' => true, 'nullable' => false],
            'Quote Id'
        ],
        'profile_id' => [
            'profile_id',
            Table::TYPE_INTEGER,
            10,
            ['unsigned' => true, 'nullable' => false],
            'Profile Id'
        ],
        'cart_data' => [
            'cart_data',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Cart Data'
        ],
        'token' => [
            'token',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Abandoned Cart Token'
        ]
    ];
    const COLUMNS_QUEUE = [
        'updated_at' => [
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            [],
            'Updated At'
        ]
    ];
    const COLUMNS_WEBHOOK = [
        'subscription_id' => [
            'subscription_id',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Subscription Id'
        ],
        'callback_url' => [
            'callback_url',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Callback Url'
        ],
        'type' => [
            'type',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => false],
            'Item Type'
        ],
        'fields' => [
            'fields',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Fields'
        ],
        'secret' => [
            'secret',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Secret'
        ],
        'backoff_config' => [
            'backoff_config',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false, 'default' => ''],
            'Exponential backoff config'
        ]
    ];
    const COLUMNS_CONFIG = [
        'section_discriminator' => [
            'section_discriminator',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Section Discriminator'
        ],
        'integration_config' => [
            'integration_config',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Installation Config'
        ],
        'api_token' => [
            'api_token',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false, 'default' => ''],
            'API Token'
        ],
        'api_token_expiry' => [
            'api_token_expiry',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false, 'default' => ''],
            'API Token Expiry'
        ],
        'error_message' => [
            'error_message',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Error Message'
        ],
        'is_active' => [
            'is_active',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '1'],
            'Is Active?'
        ],
    ];
    const TABLE_INDEXES = [
        'id',
        'quote_id',
        'store_id',
        'profile_id',
        'customer_id',
        'subscriber_id',
        'email',
        'token',
        'created_at',
        'event_type',
        'sync_status',
        'updated_at',
        'group_id',
        'is_customer',
        'is_subscriber',
        'subscriber_status',
        'type',
        'updated_at',
        'subscription_id',
        'section_discriminator',
        'is_active'
    ];
    const TABLE_FOREIGN_KEYS = [
        'store_id' => ['table' => 'store', 'column' => 'store_id'],
        'profile_id' => ['table' => BaseService::APSIS_PROFILE_TABLE, 'column' => 'id'],
        'quote_id' => ['table' => 'quote', 'column' => 'entity_id']
    ];

    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var Uninstall
     */
    private Uninstall $uninstallSchema;

    /**
     * @param BaseService $baseService
     * @param Uninstall $uninstallSchema
     */
    public function __construct(BaseService $baseService, Uninstall $uninstallSchema)
    {
        $this->baseService = $baseService;
        $this->uninstallSchema = $uninstallSchema;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        try {
            $this->baseService->log(__METHOD__);

            $setup->startSetup();

            // Remove all module tables from Magento DB
            $this->uninstallSchema->removeAllModuleTables($setup);

            foreach (self::TABLES as $table => $columns) {
                $this->createTable($table, $columns, $setup);
            }
            $setup->endSetup();
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
        $setup->endSetup();
    }

    /**
     * @param string $table
     * @param array $columns
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function createTable(string $table, array $columns, SchemaSetupInterface $setup): void
    {
        try {
            $this->baseService->debug(__METHOD__, ['Table' => $table]);

            // Create new table instance
            $tableName = $setup->getTable($table);

            // Create new table instance
            $newTable = $setup->getConnection()->newTable($tableName);

            // Go through all columns
            foreach ($this->getAllColumns($columns) as $columnName => $columnDef) {
                // Add column
                $newTable->addColumn(
                    $columnDef[0],
                    $columnDef[1],
                    $columnDef[2],
                    $columnDef[3],
                    $columnDef[4],
                );

                // Add index
                if (in_array($columnName, self::TABLE_INDEXES)) {
                    $newTable->addIndex($setup->getIdxName($tableName, [$columnName]), [$columnName]);
                }

                // Add foreign key
                if ($this->isAllowedForeignKey($columnName, $table)) {
                    $rel = self::TABLE_FOREIGN_KEYS[$columnName];
                    $newTable->addForeignKey(
                        $setup->getFkName(
                            $tableName,
                            $columnName,
                            $setup->getTable($rel['table']),
                            $rel['column']
                        ),
                        $columnName,
                        $setup->getTable($rel['table']),
                        $rel['column'],
                        Table::ACTION_CASCADE
                    );
                }
            }

            // Create table
            $newTable->setComment(ucwords(str_replace('_', ' ', $tableName)));
            $setup->getConnection()->createTable($newTable);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $column
     * @param string $table
     *
     * @return bool
     */
    private function isAllowedForeignKey(string $column, string $table): bool
    {
        if ($table === BaseService::APSIS_QUEUE_TABLE && $column === 'profile_id') {
            return false;
        }

        if (array_key_exists($column, self::TABLE_FOREIGN_KEYS)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $columnsArr
     *
     * @return array
     */
    private function getAllColumns(array $columnsArr): array
    {
        $columns = [];
        foreach ($columnsArr as $columnGroup) {
            $columns = array_merge($columns, $columnGroup);
        }
        return $columns;
    }
}
