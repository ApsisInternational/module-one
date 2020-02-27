<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Model\Sql\ExpressionFactory;
use Exception;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class Profile extends AbstractDb
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

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
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_PROFILE_TABLE, 'id');
    }

    /**
     * Profile constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param ExpressionFactory $expressionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        CustomerCollectionFactory $customerCollectionFactory,
        ExpressionFactory $expressionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        DateTime $dateTime,
        $connectionName = null
    ) {
        $this->dateTime = $dateTime;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->expressionFactory = $expressionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context, $connectionName);
    }

    /**
     * @param array $subscriberIds
     * @param int $storeId
     * @param int $status
     * @param string $msg
     *
     * @return int
     */
    public function updateSubscribersSyncStatus(array $subscriberIds, int $storeId, int $status, string $msg = '')
    {
        if (empty($subscriberIds)) {
            return 0;
        }

        $bind = ['subscriber_sync_status' => $status, 'updated_at' => $this->dateTime->formatDate(true)];
        if (strlen($msg)) {
            $bind['error_message'] = $msg;
        }

        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                $bind,
                ["subscriber_id IN (?)" => $subscriberIds, "store_id = ?" => $storeId]
            );
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array $customerIds
     * @param int $storeId
     * @param int $status
     * @param string $msg
     *
     * @return int
     */
    public function updateCustomerSyncStatus(array $customerIds, int $storeId, int $status, string $msg = '')
    {
        if (empty($customerIds)) {
            return 0;
        }

        $bind = ['customer_sync_status' => $status, 'updated_at' => $this->dateTime->formatDate(true)];
        if (strlen($msg)) {
            $bind['error_message'] = $msg;
        }

        try {
            $write = $this->getConnection();
            return $write->update(
                $this->getMainTable(),
                $bind,
                ["customer_id IN (?)" => $customerIds, "store_id = ?" => $storeId]
            );
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return 0;
        }
    }

    /**
     * @param int $storeId
     * @param array $customerIds
     *
     * @return Collection
     *
     * @throws LocalizedException
     */
    public function buildCustomerCollection($storeId, array $customerIds)
    {
        $customerLog = $this->getTable('customer_log');
        $customerCollection = $this->customerCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addNameToSelect()
            ->addAttributeToFilter('entity_id', ['in' => $customerIds]);
        $customerCollection = $this->addBillingJoinAttributesToCustomerCollection($customerCollection, $storeId);
        $customerCollection = $this->addShippingJoinAttributesToCustomerCollection($customerCollection, $storeId);
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
    }

    /**
     * @param Collection $customerCollection
     * @param int $storeId
     *
     * @return Collection
     *
     * @throws LocalizedException
     */
    private function addShippingJoinAttributesToCustomerCollection(Collection $customerCollection, $storeId)
    {
        $customerCollection = $customerCollection->joinAttribute(
            'shipping_street',
            'customer_address/street',
            'default_shipping',
            null,
            'left',
            $storeId
        )
            ->joinAttribute(
                'shipping_city',
                'customer_address/city',
                'default_shipping',
                null,
                'left',
                $storeId
            )
            ->joinAttribute(
                'shipping_country_code',
                'customer_address/country_id',
                'default_shipping',
                null,
                'left',
                $storeId
            )
            ->joinAttribute(
                'shipping_postcode',
                'customer_address/postcode',
                'default_shipping',
                null,
                'left',
                $storeId
            )
            ->joinAttribute(
                'shipping_telephone',
                'customer_address/telephone',
                'default_shipping',
                null,
                'left',
                $storeId
            )
            ->joinAttribute(
                'shipping_region',
                'customer_address/region',
                'default_shipping',
                null,
                'left',
                $storeId
            )
            ->joinAttribute(
                'shipping_company',
                'customer_address/company',
                'default_shipping',
                null,
                'left',
                $storeId
            );

        return $customerCollection;
    }

    /**
     * @param Collection $customerCollection
     * @param int $storeId
     *
     * @return Collection
     *
     * @throws LocalizedException
     */
    private function addBillingJoinAttributesToCustomerCollection(Collection $customerCollection, $storeId)
    {
        $customerCollection = $customerCollection->joinAttribute(
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

        return $customerCollection;
    }

    /**
     * @param StoreInterface $store
     * @param array $customerIds
     *
     * @return array
     */
    public function getSalesDataForCustomers(StoreInterface $store, array $customerIds)
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $salesOrderGrid = $orderCollection->getTable('sales_order_grid');
        $statuses = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_ORDER_STATUSES
        );

        $orderCollection->addFieldToSelect(['customer_id'])
            ->addExpressionFieldToSelect('total_spend', 'SUM({{grand_total}})', 'grand_total')
            ->addExpressionFieldToSelect('number_of_orders', 'COUNT({{*}})', '*')
            ->addExpressionFieldToSelect('average_order_value', 'AVG({{grand_total}})', 'grand_total')
            ->addFieldToFilter('customer_id', ['in' => $customerIds])
            ->addFieldToFilter('store_id', $store->getId())
            ->addFieldToFilter('status', ['in' => $statuses]);

        $columnData = $this->buildColumnData($salesOrderGrid, $store->getId(), $statuses);
        $orderCollection->getSelect()
            ->columns($columnData)
            ->group('customer_id');
        $orderArray = [];
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

        return $orderArray;
    }

    /**
     * @param string $salesOrderGrid
     * @param int $storeId
     * @param string $statuses
     *
     * @return array
     */
    private function buildColumnData($salesOrderGrid, $storeId, $statuses)
    {
        $statusText = $this->getConnection()->quoteInto('status in (?)', explode(",", $statuses));
        $columnData = [
            'last_order_date' => $this->expressionFactory->create(
                ["expression" => "(
                    SELECT created_at
                    FROM $salesOrderGrid
                    WHERE customer_id = main_table.customer_id AND $salesOrderGrid.store_id = $storeId AND $statusText
                    ORDER BY created_at DESC 
                    LIMIT 1
                )"]
            ),
        ];

        return $columnData;
    }
}
