<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Profile as ApsisProfile;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Sql\ExpressionFactory;
use Throwable;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Newsletter\Model\Subscriber;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class Profile extends AbstractDb implements ResourceInterface
{
    /**
     * @var CustomerCollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @var ExpressionFactory
     */
    private $expressionFactory;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_PROFILE_TABLE, 'id');
    }

    /**
     * Profile constructor.
     *
     * @param Context $context
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param ExpressionFactory $expressionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        CustomerCollectionFactory $customerCollectionFactory,
        ExpressionFactory $expressionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        DateTime $dateTime,
        $connectionName = null
    ) {
        $this->dateTime = $dateTime;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->expressionFactory = $expressionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context, $connectionName);
    }

    /**
     * @param ApsisLogHelper $apsisHelper
     * @param array $storeIds
     * @param array $profileIds
     * @param int $status
     * @param array $isEntityTypeCond
     * @param bool $secondUpdate
     */
    public function resetProfiles(
        ApsisLogHelper $apsisHelper,
        array $storeIds = [],
        array $profileIds = [],
        int $status = ApsisProfile::SYNC_STATUS_PENDING,
        array $isEntityTypeCond = [],
        bool $secondUpdate = false
    ) {
        try {
            // Update Profile type Customer
            $this->updateCustomerSyncStatus(
                [],
                $status,
                $apsisHelper,
                '',
                $storeIds,
                $profileIds,
               ['error_message' => ''],
                $isEntityTypeCond
            );

            // Update Profile type Subscriber
            $this->updateSubscribersSyncStatus(
                [],
                $status,
                $apsisHelper,
                '',
                $storeIds,
                $profileIds,
                ['error_message' => ''],
                $isEntityTypeCond,
                false
            );

            //Change all Profiles status to 5 if Profile is_[PROFILE_TYPE] is 0
            if ($secondUpdate) {
                $this->resetProfiles(
                    $apsisHelper,
                    $storeIds,
                    $profileIds,
                    ApsisProfile::SYNC_STATUS_NA,
                    ['condition' => 'is_', 'value' => ApsisProfile::NO_FLAG]
                );
            }
        } catch (Throwable $e) {
            $apsisHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array $subscriberIds
     * @param int $status
     * @param ApsisLogHelper $apsisHelper
     * @param string $msg
     * @param array $storeIds
     * @param array $profileIds
     * @param array $bind
     * @param array $isEntityTypeCond
     * @param bool $secondUpdate
     */
    public function updateSubscribersSyncStatus(
        array $subscriberIds,
        int $status,
        ApsisLogHelper $apsisHelper,
        string $msg = '',
        array $storeIds = [],
        array $profileIds = [],
        array $bind = [],
        array $isEntityTypeCond = [],
        bool $secondUpdate = true
    ) {
        try {
            $this->updateProfilSyncStatus(
                $apsisHelper,
                $status,
                ApsisProfile::TYPE_SUBSCRIBER,
                $subscriberIds,
                $msg,
                $storeIds,
                $profileIds,
                $bind,
                $isEntityTypeCond
            );

            //Reset subscriber status to 5, if not a subscriber
            if ($secondUpdate) {
                $this->updateSubscribersSyncStatus(
                    $subscriberIds,
                    ApsisProfile::SYNC_STATUS_NA,
                    $apsisHelper,
                    '',
                    [],
                    [],
                    ['error_message' => ''],
                    ['condition' => 'is_', 'value' => ApsisProfile::NO_FLAG],
                    false
                );
            }

        } catch (Throwable $e) {
            $apsisHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array $customerIds
     * @param int $status
     * @param ApsisLogHelper $apsisHelper
     * @param string $msg
     * @param array $storeIds
     * @param array $profileIds
     * @param array $bind
     * @param array $isEntityTypeCond
     *
     * @return int
     */
    public function updateCustomerSyncStatus(
        array $customerIds,
        int $status,
        ApsisLogHelper $apsisHelper,
        string $msg = '',
        array $storeIds = [],
        array $profileIds = [],
        array $bind = [],
        array $isEntityTypeCond = []
    ) {
        try {
            return $this->updateProfilSyncStatus(
                $apsisHelper,
                $status,
                ApsisProfile::TYPE_CUSTOMER,
                $customerIds,
                $msg,
                $storeIds,
                $profileIds,
                $bind,
                $isEntityTypeCond
            );
        } catch (Throwable $e) {
            $apsisHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param ApsisLogHelper $apsisHelper
     * @param int $status
     * @param string $profileType
     * @param array $entityIds
     * @param string $msg
     * @param array $storeIds
     * @param array $profileIds
     * @param array $bind
     * @param array $isEntityTypeCond
     *
     * @return int
     */
    private function updateProfilSyncStatus(
        ApsisLogHelper $apsisHelper,
        int $status,
        string $profileType,
        array $entityIds = [],
        string $msg = '',
        array $storeIds = [],
        array $profileIds = [],
        array $bind = [],
        array $isEntityTypeCond = []
    ) {
        try {
            $where = [];

            $bind[$profileType . '_sync_status'] = $status;
            $bind['updated_at'] = $this->dateTime->formatDate(true);

            if (strlen($msg)) {
                $bind['error_message'] = $msg;
            }

            if (! empty($storeIds)) {
                if ($profileType == ApsisProfile::TYPE_CUSTOMER) {
                    $where["store_id IN (?)"] = $storeIds;
                }
                if ($profileType == ApsisProfile::TYPE_SUBSCRIBER) {
                    $where["subscriber_store_id IN (?)"] = $storeIds;
                }
            }

            if (! empty($entityIds)) {
                $where[$profileType . "_id IN (?)"] = $entityIds;
            }

            if (! empty($profileIds)) {
                $where["id IN (?)"] = $profileIds;
            }

            if (! empty($isEntityTypeCond) && isset($isEntityTypeCond['condition']) &&
                isset($isEntityTypeCond['value'])
            ) {
                if ($isEntityTypeCond['condition'] == 'is_') {
                    $where["is_" . $profileType . " = ?"] = $isEntityTypeCond['value'];
                }
                if ($isEntityTypeCond['condition'] == '_sync_status') {
                    $where[$profileType . '_sync_status = ?'] = $isEntityTypeCond['value'];
                }
            }
            //$apsisCoreHelper->debug(__METHOD__, [$bind, $where]);

            return $this->getConnection()->update($this->getMainTable(), $bind, $where);
        } catch (Throwable $e) {
            $apsisHelper->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param int $storeId
     * @param array $customerIds
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array|Collection
     */
    public function buildCustomerCollection(int $storeId, array $customerIds, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $customerLog = $this->getTable('customer_log');
            $customerCollection = $this->customerCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addNameToSelect()
                ->addAttributeToFilter('entity_id', ['in' => $customerIds]);
            $customerCollection = $this->addBillingJoinAttributesToCustomerCollection(
                $customerCollection,
                $storeId,
                $apsisCoreHelper
            );
            $customerCollection = $this->addShippingJoinAttributesToCustomerCollection(
                $customerCollection,
                $storeId,
                $apsisCoreHelper
            );
            $customerCollection->getSelect()->columns([
                'last_logged_date' => $this->expressionFactory->create(
                    ["expression" => "(
                        SELECT last_login_at
                        FROM  $customerLog
                        WHERE customer_id = e.entity_id ORDER BY log_id DESC LIMIT 1
                    )"]
                ),
            ])->joinLeft(
                ['store' => $this->getTable('store')],
                "e.store_id = store.store_id",
                ['store_name' => 'name']
            )->joinLeft(
                ['website' => $this->getTable('store_website')],
                "e.website_id = website.website_id",
                ['website_name' => 'name']
            );

            return $customerCollection;
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param Collection $customerCollection
     * @param int $storeId
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return Collection
     */
    private function addShippingJoinAttributesToCustomerCollection(
        Collection $customerCollection,
        int $storeId,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        try {
            return $customerCollection
                ->joinAttribute(
                    'shipping_street',
                    'customer_address/street',
                    'default_shipping',
                    null,
                    'left',
                    $storeId
                )->joinAttribute(
                    'shipping_city',
                    'customer_address/city',
                    'default_shipping',
                    null,
                    'left',
                    $storeId
                )->joinAttribute(
                    'shipping_country_code',
                    'customer_address/country_id',
                    'default_shipping',
                    null,
                    'left',
                    $storeId
                )->joinAttribute(
                    'shipping_postcode',
                    'customer_address/postcode',
                    'default_shipping',
                    null,
                    'left',
                    $storeId
                )->joinAttribute(
                    'shipping_telephone',
                    'customer_address/telephone',
                    'default_shipping',
                    null,
                    'left',
                    $storeId
                )->joinAttribute(
                    'shipping_region',
                    'customer_address/region',
                    'default_shipping',
                    null,
                    'left',
                    $storeId
                )->joinAttribute(
                    'shipping_company',
                    'customer_address/company',
                    'default_shipping',
                    null,
                    'left',
                    $storeId
                );
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return $customerCollection;
        }
    }

    /**
     * @param Collection $customerCollection
     * @param int $storeId
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return Collection
     */
    private function addBillingJoinAttributesToCustomerCollection(
        Collection $customerCollection,
        int $storeId,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        try {
            return $customerCollection->joinAttribute(
                'billing_street',
                'customer_address/street',
                'default_billing',
                null,
                'left',
                $storeId
            )
                ->joinAttribute(
                    'billing_city',
                    'customer_address/city',
                    'default_billing',
                    null,
                    'left',
                    $storeId
                )
                ->joinAttribute(
                    'billing_country_code',
                    'customer_address/country_id',
                    'default_billing',
                    null,
                    'left',
                    $storeId
                )
                ->joinAttribute(
                    'billing_postcode',
                    'customer_address/postcode',
                    'default_billing',
                    null,
                    'left',
                    $storeId
                )
                ->joinAttribute(
                    'billing_telephone',
                    'customer_address/telephone',
                    'default_billing',
                    null,
                    'left',
                    $storeId
                )
                ->joinAttribute(
                    'billing_region',
                    'customer_address/region',
                    'default_billing',
                    null,
                    'left',
                    $storeId
                )
                ->joinAttribute(
                    'billing_company',
                    'customer_address/company',
                    'default_billing',
                    null,
                    'left',
                    $storeId
                );
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return $customerCollection;
        }
    }

    /**
     * @param StoreInterface $store
     * @param array $customerIds
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getSalesDataForCustomers(
        StoreInterface $store,
        array $customerIds,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        $orderArray = [];

        try {
            $orderCollection = $this->orderCollectionFactory->create();
            $salesOrderGrid = $orderCollection->getTable('sales_order_grid');
            $statuses = $apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::PROFILE_SYNC_ORDER_STATUSES
            );

            $orderCollection->addFieldToSelect(['customer_id'])
                ->addExpressionFieldToSelect('total_spend', 'SUM({{grand_total}})', 'grand_total')
                ->addExpressionFieldToSelect('number_of_orders', 'COUNT({{*}})', '*')
                ->addExpressionFieldToSelect('average_order_value', 'AVG({{grand_total}})', 'grand_total')
                ->addFieldToFilter('customer_id', ['in' => $customerIds])
                ->addFieldToFilter('status', ['in' => $statuses]);

            $columnData = $this->buildColumnData($salesOrderGrid, $statuses, $apsisCoreHelper);
            $orderCollection->getSelect()
                ->columns($columnData)
                ->group('customer_id');

            foreach ($orderCollection as $item) {
                $orderArray[$item->getCustomerId()] = $item->toArray(
                    [
                        'total_spend',
                        'number_of_orders',
                        'average_order_value',
                        'last_order_date'
                    ]
                );
            }

        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $orderArray;
    }

    /**
     * @param string $salesOrderGrid
     * @param string $statuses
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    private function buildColumnData(string $salesOrderGrid, string $statuses, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $statusText = $this->getConnection()->quoteInto('status in (?)', explode(",", $statuses));

            return [
                'last_order_date' => $this->expressionFactory->create(
                    ["expression" => "(
                        SELECT created_at
                        FROM $salesOrderGrid
                        WHERE customer_id = main_table.customer_id AND $statusText
                        ORDER BY created_at DESC
                        LIMIT 1
                    )"]
                ),
            ];
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return ['last_order_date' => ''];
        }
    }

    /**
     * @param AdapterInterface $connection
     * @param string $magentoTable
     * @param string $apsisTable
     * @param ApsisLogHelper $apsisLogHelper
     */
    public function fetchAndPopulateCustomers(
        AdapterInterface $connection,
        string $magentoTable,
        string $apsisTable,
        ApsisLogHelper $apsisLogHelper
    ) {
        try {
            $select = $connection->select()
                ->from(
                    ['customer' => $magentoTable],
                    [
                        'integration_uid' => $this->expressionFactory->create(["expression" => ('UUID()')]),
                        'customer_id' => 'entity_id',
                        'email',
                        'is_customer' => $this->expressionFactory->create(["expression" => ('1')]),
                        'store_id',
                        'updated_at' => $this->expressionFactory
                            ->create(["expression" => "'" . $this->dateTime->formatDate(true) . "'"])
                    ]
                );
            $sqlQuery = $select->insertFromSelect(
                $apsisTable,
                ['integration_uid', 'customer_id', 'email', 'is_customer', 'store_id', 'updated_at'],
                false
            );
            $connection->query($sqlQuery);
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param AdapterInterface $connection
     * @param string $magentoTable
     * @param string $apsisTable
     * @param ApsisLogHelper $apsisLogHelper
     */
    public function fetchAndPopulateSubscribers(
        AdapterInterface $connection,
        string $magentoTable,
        string $apsisTable,
        ApsisLogHelper $apsisLogHelper
    ) {
        try {
            $select = $connection->select()
                ->from(
                    ['subscriber' => $magentoTable],
                    [
                        'integration_uid' => $this->expressionFactory->create(["expression" => ('UUID()')]),
                        'subscriber_id',
                        'subscriber_store_id' => 'store_id',
                        'email' => 'subscriber_email',
                        'subscriber_status',
                        'is_subscriber' => $this->expressionFactory->create(["expression" => ('1')]),
                        'updated_at' => $this->expressionFactory
                            ->create(["expression" => "'" . $this->dateTime->formatDate(true) . "'"])
                    ]
                )
                ->where('subscriber_status = ?', Subscriber::STATUS_SUBSCRIBED)
                ->where('customer_id = ?', 0);

            $sqlQuery = $select->insertFromSelect(
                $apsisTable,
                [
                    'integration_uid',
                    'subscriber_id',
                    'subscriber_store_id',
                    'email',
                    'subscriber_status',
                    'is_subscriber',
                    'updated_at'
                ],
                false
            );
            $connection->query($sqlQuery);
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param AdapterInterface $connection
     * @param string $magentoTable
     * @param string $apsisTable
     * @param ApsisLogHelper $apsisLogHelper
     */
    public function updateCustomerProfiles(
        AdapterInterface $connection,
        string $magentoTable,
        string $apsisTable,
        ApsisLogHelper $apsisLogHelper
    ) {
        try {
            $select = $connection->select();
            $select
                ->from(
                    ['subscriber' => $magentoTable],
                    [
                        'subscriber_id',
                        'subscriber_status',
                        'subscriber_store_id' => 'store_id',
                        'is_subscriber' => $this->expressionFactory->create(["expression" => ('1')]),
                    ]
                )
                ->where('subscriber.subscriber_status = ?', Subscriber::STATUS_SUBSCRIBED)
                ->where('subscriber.customer_id = profile.customer_id');

            $sqlQuery = $select->crossUpdateFromSelect(['profile' => $apsisTable]);
            $connection->query($sqlQuery);
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param AdapterInterface $connection
     * @param string $magentoTable
     * @param string $apsisTable
     * @param ApsisLogHelper $apsisLogHelper
     */
    public function updateSubscriberStoreId(
        AdapterInterface $connection,
        string $magentoTable,
        string $apsisTable,
        ApsisLogHelper $apsisLogHelper
    ) {
        try {
            $select = $connection->select();
            $select->from(
                ['subscriber' => $magentoTable],
                [
                    'subscriber_store_id' => 'store_id',
                    'subscriber_sync_status' =>
                        $this->expressionFactory->create(["expression" => (ApsisProfile::SYNC_STATUS_PENDING)]),
                    'updated_at' => $this->expressionFactory
                        ->create(["expression" => "'" . $this->dateTime->formatDate(true) . "'"])
                ]
            )->where('subscriber.subscriber_id = profile.subscriber_id');

            $sqlQuery = $select->crossUpdateFromSelect(['profile' => $apsisTable]);
            $connection->query($sqlQuery);
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ApsisLogHelper $apsisLogHelper
     *
     * @return bool
     */
    public function truncateTable(ApsisLogHelper $apsisLogHelper)
    {
        try {
            $this->getConnection()->query('SET FOREIGN_KEY_CHECKS = 0');
            $this->getConnection()->truncateTable($this->getMainTable());
            $this->getConnection()->query('SET FOREIGN_KEY_CHECKS = 1');
            return true;
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param ApsisLogHelper $apsisLogHelper
     *
     * @return bool
     */
    public function populateProfilesTable(ApsisLogHelper $apsisLogHelper)
    {
        try {
            $magentoSubscriberTable = $this->getTable('newsletter_subscriber');

            //Fetch customers to profile table
            $this->fetchAndPopulateCustomers(
                $this->getConnection(),
                $this->getTable('customer_entity'),
                $this->getMainTable(),
                $apsisLogHelper
            );

            //Fetch subscribers to profile table
            $this->fetchAndPopulateSubscribers(
                $this->getConnection(),
                $magentoSubscriberTable,
                $this->getMainTable(),
                $apsisLogHelper
            );

            //Update customers with profile id in profile table
            $this->updateCustomerProfiles(
                $this->getConnection(),
                $magentoSubscriberTable,
                $this->getMainTable(),
                $apsisLogHelper
            );
            return true;
        } catch (Throwable $e) {
            $apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function cleanupRecords(int $day, ApsisCoreHelper $apsisCoreHelper)
    {
        // Not needed for profiles
    }

    /**
     * @param ApsisLogHelper|ApsisCoreHelper $apsisHelper
     * @param string $andCondition
     *
     * @return bool
     */
    public function deleteAllModuleConfig($apsisHelper, string $andCondition = '')
    {
        try {
            $connection = $this->getConnection();
            $connection->delete($this->getTable('core_config_data'), "path LIKE 'apsis_one%' $andCondition");
            $apsisHelper->cleanCache();
            return true;
        } catch (Throwable $e) {
            $apsisHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
