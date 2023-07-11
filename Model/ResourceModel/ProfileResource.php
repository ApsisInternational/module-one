<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Service\BaseService;
use Apsis\One\Service\ProfileService;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Api\Data\StoreInterface;
use Throwable;

class ProfileResource extends AbstractResource
{
    const RESOURCE_MODEL = BaseService::APSIS_PROFILE_TABLE;
    const SQL_CONSENT = '(
        SELECT CASE
            WHEN subscriber_status = %s THEN true
            WHEN subscriber_status = %s THEN false
            ELSE null
        END
    )';
    const SQL_CURRENCY = '(SELECT "%s")';

    /**
     * @var CustomerCollectionFactory
     */
    private CustomerCollectionFactory $customerCollectionFactory;

    /**
     * @var ExpressionFactory
     */
    private ExpressionFactory $expressionFactory;

    /**
     * @var SubscriberCollectionFactory
     */
    private SubscriberCollectionFactory $subscriberCollectionFactory;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param ExpressionFactory $expressionFactory
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        CustomerCollectionFactory $customerCollectionFactory,
        ExpressionFactory $expressionFactory,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $dateTime, $connectionName);
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->expressionFactory = $expressionFactory;
    }

    /**
     * @param string $expressionString
     *
     * @return Expression
     */
    private function getExpressionModel(string $expressionString): Expression
    {
        return $this->expressionFactory->create(['expression' => $expressionString]);
    }

    /**
     * @param AdapterInterface $connection
     * @param string $magentoTable
     * @param string $apsisTable
     * @param BaseService $service
     *
     * @return void
     */
    public function fetchAndPopulateCustomers(
        AdapterInterface $connection,
        string $magentoTable,
        string $apsisTable,
        BaseService $service
    ): void {
        try {
            $select = $connection->select()
                ->from(
                    [ProfileService::TYPE_CUSTOMER => $magentoTable],
                    [
                        'customer_id' => 'entity_id',
                        'group_id',
                        'email',
                        'is_customer' => $this->getExpressionModel('1'),
                        'store_id',
                        'updated_at' => $this->expressionFactory
                            ->create(['expression' => "'" . $this->dateTime->formatDate(true) . "'"])
                    ]
                );
            $sqlQuery = $select->insertFromSelect(
                $apsisTable,
                ['customer_id', 'group_id', 'email', 'is_customer', 'store_id', 'updated_at'],
                false
            );
            $connection->query($sqlQuery);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param AdapterInterface $connection
     * @param string $magentoTable
     * @param string $apsisTable
     * @param BaseService $service
     *
     * @return void
     */
    public function fetchAndPopulateSubscribers(
        AdapterInterface $connection,
        string $magentoTable,
        string $apsisTable,
        BaseService $service
    ): void {
        try {
            $select = $connection->select()
                ->from(
                    [ProfileService::TYPE_SUBSCRIBER => $magentoTable],
                    [
                        'subscriber_id',
                        'store_id' => 'store_id',
                        'email' => 'subscriber_email',
                        'is_subscriber' => $this->getExpressionModel('1'),
                        'subscriber_status',
                        'updated_at' => $this->expressionFactory
                            ->create(['expression' => "'" . $this->dateTime->formatDate(true) . "'"])
                    ]
                )->where('customer_id = ?', 0);
            $sqlQuery = $select->insertFromSelect(
                $apsisTable,
                [
                    'subscriber_id',
                    'store_id',
                    'email',
                    'is_subscriber',
                    'subscriber_status',
                    'updated_at'
                ],
                false
            );
            $connection->query($sqlQuery);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param AdapterInterface $connection
     * @param string $magentoTable
     * @param string $apsisTable
     * @param BaseService $service
     *
     * @return void
     */
    public function updateCustomerProfiles(
        AdapterInterface $connection,
        string $magentoTable,
        string $apsisTable,
        BaseService $service
    ): void {
        try {
            $select = $connection->select();
            $select->from(
                [ProfileService::TYPE_SUBSCRIBER => $magentoTable],
                [
                    'subscriber_id',
                    'subscriber_status',
                    'is_subscriber' => $this->getExpressionModel('1'),
                ]
            )->where('subscriber.customer_id = profile.customer_id');
            $sqlQuery = $select->crossUpdateFromSelect(['profile' => $apsisTable]);
            $connection->query($sqlQuery);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param BaseService $service
     *
     * @return void
     */
    public function populateProfilesTable(BaseService $service): void
    {
        try {
            $magentoSubscriberTable = $this->getTable('newsletter_subscriber');

            // Fetch customers to profile table
            $this->fetchAndPopulateCustomers(
                $this->getConnection(),
                $this->getTable('customer_entity'),
                $this->getMainTable(),
                $service
            );

            // Fetch subscribers to profile table
            $this->fetchAndPopulateSubscribers(
                $this->getConnection(),
                $magentoSubscriberTable,
                $this->getMainTable(),
                $service
            );

            // Update customers with profile id in profile table
            $this->updateCustomerProfiles(
                $this->getConnection(),
                $magentoSubscriberTable,
                $this->getMainTable(),
                $service
            );

            // Fill profile data column
            $this->fillProfileDataColumnForAllProfiles($service);
        } catch (Throwable $e) {
            $service->log('Unable to complete populate Profile table action.');
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param BaseService $service
     *
     * @return void
     */
    private function fillProfileDataColumnForAllProfiles(BaseService $service): void
    {
        try {
            foreach ($service->getStores() as $store) {
                $currency = $service->getStoreCurrency($store->getId());
                $this->fillProfileDataColumnForCustomers($store, $currency, $service);
                $this->fillProfileDataColumnForSubscribers($store, $currency, $service);
            }
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $currency
     * @param BaseService $service
     *
     * @return void
     */
    private function fillProfileDataColumnForCustomers(
        StoreInterface $store,
        string $currency,
        BaseService $service
    ): void {
        try {
            $expString = $this->buildDataQueryForCustomer($store, $service, $currency);
            if (empty($expString)) {
                return;
            }

            $select = $this->getConnection()
                ->select()
                ->from(
                    [ProfileService::TYPE_CUSTOMER => $this->getCustomerCollection()->getMainTable()],
                    ['profile_data' => $this->getExpressionModel($expString)]
                )->where('customer.store_id = ?', $store->getId())
                ->where('customer.entity_id = profile.customer_id');
            $sqlQuery = $select->crossUpdateFromSelect(['profile' => $this->getMainTable()]);
            $this->getConnection()->query($sqlQuery);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @return CustomerCollection
     */
    private function getCustomerCollection(): CustomerCollection
    {
        return $this->customerCollectionFactory->create();
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $service
     * @param string $currency
     *
     * @param string $id
     * @return string|null
     */
    public function buildDataQueryForCustomer(
        StoreInterface $store,
        BaseService $service,
        string $currency,
        string $id = 'customer.entity_id'
    ): ?string {
        try {
            $collection = $this->getCustomerCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('store_id', $store->getId());

            $phText = '(SELECT aov.value FROM %s aov WHERE aov.option_id = e.gender AND aov.store_id = 0 LIMIT 1)';
            $collection->getSelect()->columns(
                [
                    'gender_text' => $this->getExpressionModel(
                        sprintf($phText, $this->getTable('eav_attribute_option_value'))
                    ),
                    'shop_currency' => $this->getExpressionModel(sprintf(self::SQL_CURRENCY, $currency))
                ]
            );

            $this->addBillingAddress($collection, $store->getId(), $service);
            $this->addDynamicAttributes($collection, $service);
            $this->joinTables($collection, $service);

            $collection->getSelect()->group(['e.entity_id', 'subscriber.subscriber_id']);
            return $this->getExpressionString($collection->getSelect()->assemble(), $id);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param CustomerCollection $collection
     * @param BaseService $service
     *
     * @return void
     */
    private function joinTables(CustomerCollection $collection, BaseService $service): void
    {
        try {
            $collection->getSelect()->joinLeft(
                ['group' => $this->getTable('customer_group')],
                'e.group_id = group.customer_group_id',
                ['customer_group_id', 'customer_group_code']
            )->joinLeft(
                ['store' => $this->getTable('store')],
                'e.store_id = store.store_id',
                ['store_name' => 'name']
            )->joinLeft(
                [ProfileService::TYPE_SUBSCRIBER => $this->getTable('newsletter_subscriber')],
                'e.entity_id = subscriber.customer_id',
                [
                    'subscribed' => $this->getExpressionModel(
                        sprintf(self::SQL_CONSENT, Subscriber::STATUS_SUBSCRIBED, Subscriber::STATUS_UNSUBSCRIBED)
                    )
                ]
            )->joinLeft(
                ['sales' => $this->getTable('sales_order')],
                'e.entity_id = sales.customer_id',
                [
                    'total_orders_value' => 'SUM(grand_total)',
                    'average_order_value' => 'AVG(grand_total)'
                ]
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param CustomerCollection $collection
     * @param BaseService $service
     *
     * @return void
     */
    private function addDynamicAttributes(CustomerCollection $collection, BaseService $service): void
    {
        try {
            $salesOrderGrid = $this->getTable('sales_order_grid');
            $collection->getSelect()->columns([
                'last_login_date' => $this->getExpressionModel(
                    sprintf('(
                        SELECT last_login_at
                        FROM %s
                        WHERE customer_id = e.entity_id
                        ORDER BY log_id DESC
                        LIMIT 1
                    )', $this->getTable('customer_log'))
                ),
                'last_order_date' => $this->getExpressionModel(
                    "(
                        SELECT created_at
                        FROM $salesOrderGrid
                        WHERE customer_id = e.entity_id
                        ORDER BY created_at DESC
                        LIMIT 1
                    )"
                ),
                'last_order_value' => $this->getExpressionModel(
                    "(
                        SELECT grand_total
                        FROM $salesOrderGrid
                        WHERE customer_id = e.entity_id
                        ORDER BY created_at DESC
                        LIMIT 1
                    )"
                ),
                'total_orders' => $this->getExpressionModel(
                    sprintf('(
                        SELECT COUNT(*)
                        FROM %s
                        WHERE customer_id = e.entity_id
                        GROUP BY customer_id
                        LIMIT 1
                    )', $this->getTable('sales_order'))
                ),
                'last_product_review_date' => $this->getExpressionModel(
                    sprintf('(
                        SELECT r.created_at
                        FROM %s r
                        LEFT JOIN %s rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        ORDER BY r.created_at DESC
                        LIMIT 1
                    )', $this->getTable('review'), $this->getTable('review_detail'))
                ),
                'total_product_reviews' => $this->getExpressionModel(
                    sprintf('(
                        SELECT COUNT(*)
                        FROM %s r
                        LEFT JOIN %s rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        GROUP BY customer_id
                        LIMIT 1
                    )', $this->getTable('review'), $this->getTable('review_detail'))
                )
            ]);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param CustomerCollection $collection
     * @param int $storeId
     * @param BaseService $service
     *
     * @return void
     */
    private function addBillingAddress(CustomerCollection $collection, int $storeId, BaseService $service): void
    {
        try {
            $collection->joinAttribute(
                'billing_street',
                'customer_address/street',
                'default_billing',
                null,
                'left',
                $storeId
            )->joinAttribute(
                'billing_city',
                'customer_address/city',
                'default_billing',
                null,
                'left',
                $storeId
            )->joinAttribute(
                'billing_country_code',
                'customer_address/country_id',
                'default_billing',
                null,
                'left',
                $storeId
            )->joinAttribute(
                'billing_postcode',
                'customer_address/postcode',
                'default_billing',
                null,
                'left',
                $storeId
            )->joinAttribute(
                'billing_telephone',
                'customer_address/telephone',
                'default_billing',
                null,
                'left',
                $storeId
            )->joinAttribute(
                'billing_region',
                'customer_address/region',
                'default_billing',
                null,
                'left',
                $storeId
            );
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $currency
     * @param BaseService $service
     *
     * @return void
     */
    private function fillProfileDataColumnForSubscribers(
        StoreInterface $store,
        string $currency,
        BaseService $service
    ): void {
        try {
            $expString = $this->buildDataQueryForSubscriber($store, $service, $currency);
            if (empty($expString)) {
                return;
            }

            $select = $this->getConnection()
                ->select()
                ->from(
                    [ProfileService::TYPE_SUBSCRIBER => $this->getSubscriberCollection()->getMainTable()],
                    ['profile_data' => $this->getExpressionModel($expString)]
                )
                ->where('subscriber.store_id = ?', $store->getId())
                ->where('subscriber.customer_id = ?', 0)
                ->where('subscriber.subscriber_id = profile.subscriber_id');

            $sqlQuery = $select->crossUpdateFromSelect(['profile' => $this->getMainTable()]);
            $this->getConnection()->query($sqlQuery);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @return SubscriberCollection
     */
    private function getSubscriberCollection(): SubscriberCollection
    {
        return $this->subscriberCollectionFactory->create();
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $service
     * @param string $currency
     * @param string $id
     *
     * @return string|null
     */
    public function buildDataQueryForSubscriber(
        StoreInterface $store,
        BaseService $service,
        string $currency,
        string $id = 'subscriber.subscriber_id'
    ): ?string {
        try {
            $collection = $this->getSubscriberCollection()
                ->addFieldToSelect(
                    [
                        'store_id',
                        'email' => 'subscriber_email',
                        'subscribed' => $this->getExpressionModel(
                            sprintf(self::SQL_CONSENT, Subscriber::STATUS_SUBSCRIBED, Subscriber::STATUS_UNSUBSCRIBED)
                        )
                    ]
                )->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.customer_id', 0);

            $nullExpr = $this->getExpressionModel('NULL');
            $collection->getSelect()
                ->columns(
                    [
                        'dob' => $nullExpr,
                        'gender_text' => $nullExpr,
                        'created_at' => $nullExpr,
                        'last_login_date' => $nullExpr,
                        'prefix' => $nullExpr,
                        'firstname' => $nullExpr,
                        'middlename' => $nullExpr,
                        'lastname' => $nullExpr,
                        'billing_street' => $nullExpr,
                        'billing_postcode' => $nullExpr,
                        'billing_region' => $nullExpr,
                        'billing_city' => $nullExpr,
                        'billing_country_code' => $nullExpr,
                        'billing_telephone' => $nullExpr,
                        'customer_group_id' => $nullExpr,
                        'customer_group_code' => $nullExpr,
                        'total_product_reviews' => $nullExpr,
                        'last_product_review_date' => $nullExpr,
                        'total_orders' => $nullExpr,
                        'total_orders_value' => $nullExpr,
                        'average_order_value' => $nullExpr,
                        'last_order_value' => $nullExpr,
                        'last_order_date' => $nullExpr,
                        'shop_currency' => $this->getExpressionModel(sprintf(self::SQL_CURRENCY, $currency))
                    ]
                )->joinLeft(
                    ['store' => $this->getTable('store')],
                    'main_table.store_id = store.store_id',
                    ['store_name' => 'name']
                );

            return $this->getExpressionString($collection->getSelect()->assemble(), $id, true);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Profile schema, see function getProfileSchema in class \Apsis\One\Controller\Api\Profiles\Index
     * IMPORTANT. JSON object keys needs to match with \Apsis\One\Controller\Api\Profiles\Index::getProfileSchema
     *
     *
     * @param string $select
     * @param string $id
     * @param bool $forSubscriber
     *
     * @return string
     */
    private function getExpressionString(string $select, string $id, bool $forSubscriber = false): string
    {
        $pColumn = $forSubscriber ? 'subscriber_id' : 'entity_id';
        return sprintf(
            "(SELECT
                        JSON_OBJECT(
                            'email', email,
                            'shop_id', store_id,
                            'shop_name', store_name,
                            'subscribed', subscribed,
                            'date_of_birth', dob,
                            'gender', gender_text,
                            'created_at', created_at,
                            'last_login_date', last_login_date,
                            'title', prefix,
                            'first_name', firstname,
                            'middle_name', middlename,
                            'last_name', lastname,
                            'street', billing_street,
                            'postcode', billing_postcode,
                            'city', billing_city,
                            'region', billing_region,
                            'country', billing_country_code,
                            'phone', billing_telephone,
                            'list_id', customer_group_id,
                            'list_name', customer_group_code,
                            'shop_currency', shop_currency,
                            'total_product_reviews', total_product_reviews,
                            'last_product_review_date', last_product_review_date,
                            'total_orders', total_orders,
                            'total_orders_value', total_orders_value,
                            'average_order_value', average_order_value,
                            'last_order_value', last_order_value,
                            'last_order_date', last_order_date
                        )
                    FROM (%s) AS q
                    WHERE (q.%s = %s))",
            $select,
            $pColumn,
            $id
        );
    }
}
