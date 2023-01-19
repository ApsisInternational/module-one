<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Registry;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Throwable;

class UpgradeSchema implements UpgradeSchemaInterface
{
    const REGISTRY_NAME = 'APSIS_SCHEMA_RUN';

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $logHelper;

    /**
     * UpgradeSchema constructor.
     *
     * @param Registry $registry
     * @param ApsisLogHelper $logHelper
     */
    public function __construct(Registry $registry, ApsisLogHelper $logHelper)
    {
        $this->registry = $registry;
        $this->logHelper = $logHelper;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        try {
            $this->logHelper->log(__METHOD__);
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
            if (version_compare($context->getVersion(), '1.9.4', '<')) {
                $this->upgradeOneNineFour($setup);
            }
        } catch (Throwable $e) {
            $setup->endSetup();
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function upgradeOneNineFour(SchemaSetupInterface $setup): void
    {
        try {
            $this->logHelper->log(__METHOD__);
            $tableName = $setup->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
            $columnName = 'subscriber_store_id';

            //Column doesn't exist then create it.
            if (! $setup->getConnection()->tableColumnExists($tableName, $columnName)) {
                //Add Column
                $setup->getConnection()->addColumn(
                    $tableName,
                    $columnName,
                    [
                        'type' => Table::TYPE_SMALLINT,
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Subscriber Store Id'
                    ]
                );
                //Add Index
                $setup->getConnection()->addIndex(
                    $tableName,
                    $setup->getIdxName($tableName, [$columnName]),
                    [$columnName]
                );
                $this->registry->register(self::REGISTRY_NAME, 1, true);
            }

            //Remove foreign key
            $setup->getConnection()->dropForeignKey(
                $tableName,
                $setup->getFkName(
                    $tableName,
                    'store_id',
                    $setup->getTable('store'),
                    'store_id'
                )
            );

            //Modify column
            if ($setup->getConnection()->tableColumnExists($tableName, 'store_id')) {
                $setup->getConnection()->modifyColumn(
                    $tableName,
                    'store_id',
                    [
                        'type' => Table::TYPE_SMALLINT,
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Store ID'
                    ]
                );
            }

            //Remove column
            $setup->getConnection()->dropColumn($tableName, 'website_id');
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function upgradeOneNineOne(SchemaSetupInterface $setup): void
    {
        try {
            $this->logHelper->log(__METHOD__);
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
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function upgradeOneEightZero(SchemaSetupInterface $setup): void
    {
        try {
            $this->logHelper->log(__METHOD__);
            $setup->getConnection()->dropColumn(
                $setup->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                'topic_subscription'
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function upgradeOneOneZero(SchemaSetupInterface $setup): void
    {
        try {
            $this->logHelper->log(__METHOD__);
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
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     *
     * @return void
     */
    private function upgradeOneThreeZero(SchemaSetupInterface $setup): void
    {
        try {
            $this->logHelper->log(__METHOD__);
            $setup->getConnection()->dropForeignKey(
                $setup->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE),
                $setup->getFkName(
                    $setup->getTable(ApsisCoreHelper::APSIS_ABANDONED_TABLE),
                    'customer_id',
                    $setup->getTable('customer_entity'),
                    'entity_id'
                )
            );
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
        }
    }
}
