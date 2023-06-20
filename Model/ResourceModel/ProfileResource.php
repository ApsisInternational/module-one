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
use Magento\Store\Api\Data\StoreInterface;
use Throwable;

class ProfileResource extends AbstractResource
{
    const RESOURCE_MODEL = BaseService::APSIS_PROFILE_TABLE;

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
                    [ProfileService::ENTITY_CUSTOMER => $magentoTable],
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
                    [ProfileService::ENTITY_TYPE_SUBSCRIBER => $magentoTable],
                    [
                        'subscriber_id',
                        'store_id' => 'store_id',
                        'email' => 'subscriber_email',
                        'is_subscriber' => $this->getExpressionModel('1'),
                        'subscriber_status',
                        'updated_at' => $this->expressionFactory
                            ->create(['expression' => "'" . $this->dateTime->formatDate(true) . "'"])
                    ]
                )
                ->where('customer_id = ?', 0);

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
            $select
                ->from(
                    [ProfileService::ENTITY_TYPE_SUBSCRIBER => $magentoTable],
                    [
                        'subscriber_id',
                        'subscriber_status',
                        'is_subscriber' => $this->getExpressionModel('1'),
                    ]
                )
                ->where('subscriber.customer_id = profile.customer_id');

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
                $this->fillProfileDataColumnForCustomers($store, $service);
                $this->fillProfileDataColumnForSubscribers($store, $service);
            }
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $service
     *
     * @return void
     */
    private function fillProfileDataColumnForCustomers(StoreInterface $store, BaseService $service): void
    {
        try {
            $expString = $this->buildProfileDataQueryForCustomer($store, $service);
            if (empty($expString)) {
                return;
            }

            $select = $this->getConnection()
                ->select()
                ->from(
                    [ProfileService::ENTITY_CUSTOMER => $this->getCustomerCollection()->getMainTable()],
                    ['profile_data' => $this->getExpressionModel($expString)]
                )
                ->where('customer.store_id = ?', $store->getId())
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
     * @param string $id
     *
     * @return string|null
     */
    public function buildProfileDataQueryForCustomer(
        StoreInterface $store,
        BaseService $service,
        string $id = 'customer.entity_id'
    ): ?string {
        try {
            $customerLog = $this->getTable('customer_log');
            $salesOrderGrid = $this->getTable('sales_order_grid');
            $sales = $this->getTable('sales_order');
            $eavAttributeOptionValue = $this->getTable('eav_attribute_option_value');
            $review = $this->getTable('review');
            $reviewDetail = $this->getTable('review_detail');

            $collection = $this->getCustomerCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('store_id', $store->getId());

            $collection = $this->addBillingJoinAttributesToCustomerCollection(
                $collection,
                $store->getId(),
                $service
            );
            $collection = $this->addShippingJoinAttributesToCustomerCollection(
                $collection,
                $store->getId(),
                $service
            );

            $collection->getSelect()->columns([
                'last_logged_date' => $this->getExpressionModel(
                    "(
                        SELECT last_login_at
                        FROM $customerLog
                        WHERE customer_id = e.entity_id
                        ORDER BY log_id DESC
                        LIMIT 1
                    )"
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
                'number_of_orders' => $this->getExpressionModel(
                    "(
                        SELECT COUNT(*)
                        FROM $sales
                        WHERE customer_id = e.entity_id
                        GROUP BY customer_id
                        LIMIT 1
                    )"
                ),
                'gender_text' => $this->getExpressionModel(
                    "(
                        SELECT aov.value
                        FROM $eavAttributeOptionValue aov
                        WHERE aov.option_id = e.gender AND aov.store_id = 0
                        LIMIT 1
                    )"
                ),
                'last_review_date' => $this->getExpressionModel(
                    "(
                        SELECT r.created_at
                        FROM $review r
                        LEFT JOIN $reviewDetail rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        ORDER BY r.created_at DESC
                        LIMIT 1
                    )"
                ),
                'review_count' => $this->getExpressionModel(
                    "(
                        SELECT COUNT(*)
                        FROM $review r
                        LEFT JOIN $reviewDetail rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        GROUP BY customer_id
                        LIMIT 1
                    )"
                ),
            ])->joinLeft(
                ['group' => $this->getTable('customer_group')],
                'e.group_id = group.customer_group_id',
                ['customer_group_code']
            )->joinLeft(
                ['store' => $this->getTable('store')],
                'e.store_id = store.store_id',
                ['store_name' => 'name']
            )->joinLeft(
                ['website' => $this->getTable('store_website')],
                'e.website_id = website.website_id',
                ['website_name' => 'name']
            )->joinLeft(
                [ProfileService::ENTITY_TYPE_SUBSCRIBER => $this->getTable('newsletter_subscriber')],
                'e.entity_id = subscriber.customer_id',
                ['subscriber_id', 'subscriber_status', 'change_status_at']
            )->joinLeft(
                ['sales' => $sales],
                'e.entity_id = sales.customer_id',
                [
                    'total_spend' => 'SUM(grand_total)',
                    'average_order_value' => 'AVG(grand_total)'
                ]
            )->group(['e.entity_id', 'subscriber.subscriber_id']);

            return $this->getExpressionString($collection->getSelect()->assemble(), $id);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param CustomerCollection $customerCollection
     * @param int $storeId
     * @param BaseService $service
     *
     * @return CustomerCollection
     */
    private function addShippingJoinAttributesToCustomerCollection(
        CustomerCollection $customerCollection,
        int $storeId,
        BaseService $service
    ): CustomerCollection {
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
            $service->logError(__METHOD__, $e);
            return $customerCollection;
        }
    }

    /**
     * @param CustomerCollection $customerCollection
     * @param int $storeId
     * @param BaseService $service
     *
     * @return CustomerCollection
     */
    private function addBillingJoinAttributesToCustomerCollection(
        CustomerCollection $customerCollection,
        int $storeId,
        BaseService $service
    ): CustomerCollection {
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
            $service->logError(__METHOD__, $e);
            return $customerCollection;
        }
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $service
     *
     * @return void
     */
    private function fillProfileDataColumnForSubscribers(StoreInterface $store, BaseService $service): void
    {
        try {
            $expString = $this->buildProfileDataQueryForSubscriber($store, $service);
            if (empty($expString)) {
                return;
            }

            $select = $this->getConnection()
                ->select()
                ->from(
                    [ProfileService::ENTITY_TYPE_SUBSCRIBER => $this->getSubscriberCollection()->getMainTable()],
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
     * @param string $id
     *
     * @return string|null
     */
    public function buildProfileDataQueryForSubscriber(
        StoreInterface  $store,
        BaseService $service,
        string $id = 'subscriber.subscriber_id'
    ): ?string {
        try {
            $collection = $this->getSubscriberCollection()
                ->addFieldToSelect(
                    [
                        'store_id',
                        'change_status_at',
                        'customer_id' =>$this->expressionFactory
                            ->create(['expression' => 'IF(customer_id="0", NULL, customer_id)']),
                        'subscriber_email',
                        'subscriber_status'
                    ]
                )
                ->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.customer_id', 0);

            $nullExpr = $this->getExpressionModel('NULL');
            $collection->getSelect()
                ->columns(
                    [
                        'title' => $nullExpr,
                        'firstname' => $nullExpr,
                        'lastname' => $nullExpr,
                        'dob' => $nullExpr,
                        'gender' => $nullExpr,
                        'created_at' => $nullExpr,
                        'last_logged_date' => $nullExpr,
                        'customer_group' => $nullExpr,
                        'review_count' => $nullExpr,
                        'last_review_date' => $nullExpr,
                        'billing_street' => $nullExpr,
                        'billing_state' => $nullExpr,
                        'billing_city' => $nullExpr,
                        'billing_country' => $nullExpr,
                        'billing_postcode' => $nullExpr,
                        'billing_telephone' => $nullExpr,
                        'billing_company' => $nullExpr,
                        'delivery_street' => $nullExpr,
                        'delivery_city' => $nullExpr,
                        'delivery_state' => $nullExpr,
                        'delivery_country' => $nullExpr,
                        'delivery_postcode' => $nullExpr,
                        'delivery_telephone' => $nullExpr,
                        'delivery_company' => $nullExpr,
                        'last_order_date' => $nullExpr,
                        'number_of_orders' => $nullExpr,
                        'average_order_value' => $nullExpr,
                        'total_spend' => $nullExpr
                    ]
                )->joinLeft(
                    ['store' => $this->getTable('store')],
                    'main_table.store_id = store.store_id',
                    ['store_name' => 'name']
                )->joinLeft(
                    ['website' => $this->getTable('store_website')],
                    'store.website_id = website.website_id',
                    ['website_name' => 'name', 'website_id']
                );

            return $this->getExpressionString($collection->getSelect()->assemble(), $id, true);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Profile schema, see function getProfileSchema in class Apsis\One\Controller\Api\Schema\Profile
     *
     * @param string $select
     * @param string $id
     * @param bool $forSubscriber
     *
     * @return string
     */
    private function getExpressionString(string $select, string $id, bool $forSubscriber = false): string
    {
        if ($forSubscriber) {
            return sprintf(
                "(SELECT
                            JSON_OBJECT(
                                'website_id', website_id,
                                'store_id', store_id,
                                'website_name', website_name,
                                'store_name', store_name,
                                'email', subscriber_email,
                                'subscriber_id', subscriber_id,
                                'subscriber_status', subscriber_status,
                                'change_status_at', change_status_at,
                                'title', title,
                                'customer_id', customer_id,
                                'firstname', firstname,
                                'lastname', lastname,
                                'dob', dob,
                                'gender', gender,
                                'created_at', created_at,
                                'last_logged_date', last_logged_date,
                                'customer_group', customer_group,
                                'review_count', review_count,
                                'last_review_date', last_review_date,
                                'billing_street', billing_street,
                                'billing_state', billing_state,
                                'billing_city', billing_city,
                                'billing_country', billing_country,
                                'billing_postcode', billing_postcode,
                                'billing_telephone', billing_telephone,
                                'billing_company', billing_company,
                                'delivery_street', delivery_street,
                                'delivery_city', delivery_city,
                                'delivery_state', delivery_state,
                                'delivery_country', delivery_country,
                                'delivery_postcode', delivery_postcode,
                                'delivery_telephone', delivery_telephone,
                                'delivery_company', delivery_company,
                                'last_order_date', last_order_date,
                                'number_of_orders', number_of_orders,
                                'average_order_value', average_order_value,
                                'total_spend', total_spend
                            )
                        FROM (%s) AS q
                        WHERE (q.subscriber_id = %s))",
                $select,
                $id
            );
        }

        return sprintf(
            "(SELECT
                        JSON_OBJECT(
                            'website_id', website_id,
                            'store_id', store_id,
                            'website_name', website_name,
                            'store_name', store_name,
                            'email', email,
                            'subscriber_id', subscriber_id,
                            'subscriber_status', subscriber_status,
                            'change_status_at', change_status_at,
                            'title', prefix,
                            'customer_id', entity_id,
                            'firstname', firstname,
                            'lastname', lastname,
                            'dob', dob,
                            'gender', gender_text,
                            'created_at', created_at,
                            'last_logged_date', last_logged_date,
                            'customer_group', customer_group_code,
                            'review_count', review_count,
                            'last_review_date', last_review_date,
                            'billing_street', billing_street,
                            'billing_state', billing_region,
                            'billing_city', billing_city,
                            'billing_country', billing_country_code,
                            'billing_postcode', billing_postcode,
                            'billing_telephone', billing_telephone,
                            'billing_company', billing_company,
                            'delivery_street', shipping_street,
                            'delivery_city', shipping_city,
                            'delivery_state', shipping_region,
                            'delivery_country', shipping_country_code,
                            'delivery_postcode', shipping_postcode,
                            'delivery_telephone', shipping_telephone,
                            'delivery_company', shipping_company,
                            'last_order_date', last_order_date,
                            'number_of_orders', number_of_orders,
                            'average_order_value', average_order_value,
                            'total_spend', total_spend
                        )
                    FROM (%s) AS q
                    WHERE (q.entity_id = %s))",
            $select,
            $id
        );
    }
}
