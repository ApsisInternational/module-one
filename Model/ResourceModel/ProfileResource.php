<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\ResourceModel\Profile\ProfileCollection;
use Apsis\One\Model\ResourceModel\Profile\ProfileCollectionFactory;
use Apsis\One\Service\BaseService;
use Apsis\One\Service\ProfileService;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Api\Data\StoreInterface;
use Throwable;

class ProfileResource extends AbstractResource
{
    const RESOURCE_MODEL = BaseService::APSIS_PROFILE_TABLE;
    const QUERY_LIMIT = 5000;

    /**
     * @var CustomerCollectionFactory
     */
    private CustomerCollectionFactory $customerCollectionFactory;

    /**
     * @var ExpressionFactory
     */
    private ExpressionFactory $expressionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param ExpressionFactory $expressionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        CustomerCollectionFactory $customerCollectionFactory,
        ExpressionFactory $expressionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $dateTime, $connectionName);
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->expressionFactory = $expressionFactory;
        $this->profileCollectionFactory = $profileCollectionFactory;
    }

    /**
     * @param string $expressionString
     *
     * @return Expression
     */
    private function getSqlExpression(string $expressionString): Expression
    {
        return $this->expressionFactory->create(['expression' => $expressionString]);
    }

    /**
     * @return CustomerCollection
     */
    private function getCustomerCollectionInstance(): CustomerCollection
    {
        return $this->customerCollectionFactory->create();
    }

    /**
     * @return ProfileCollection
     */
    private function getProfileCollectionInstance(): ProfileCollection
    {
        return $this->profileCollectionFactory->create();
    }

    /**
     * @param BaseService $service
     *
     * @return void
     */
    public function populateProfilesTable(BaseService $service): void
    {
        $this->fetchAndInsertCustomersForAllStores($service);
        $this->aggregateProfileDataForAllStores($service);
    }

    /**
     * @param BaseService $service
     *
     * @return void
     */
    private function fetchAndInsertCustomersForAllStores(BaseService $service): void
    {
        try {
            $customerIds = $this->getCustomerCollectionInstance()->getAllIds();
            foreach (array_chunk($customerIds, self::QUERY_LIMIT) as $filterChunk) {
                $select = $this->getConnection()->select()
                    ->from(
                        [ProfileService::TYPE_CUSTOMER => $this->getTable('customer_entity')],
                        [
                            'customer_id' => 'entity_id',
                            'group_id',
                            'email',
                            'is_customer' => $this->getSqlExpression('1'),
                            'store_id',
                            'updated_at' => $this->expressionFactory
                                ->create(['expression' => sprintf("'%s'", $this->dateTime->formatDate(true))])
                        ]
                    )->where('entity_id IN (?)', $filterChunk);
                $insertQuery = $select->insertFromSelect(
                    $this->getMainTable(),
                    ['customer_id', 'group_id', 'email', 'is_customer', 'store_id', 'updated_at'],
                    false
                );
                $this->getConnection()->query($insertQuery);
            }
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param BaseService $service
     *
     * @return void
     */
    private function aggregateProfileDataForAllStores(BaseService $service): void
    {
        try {
            foreach ($service->getStores() as $store) {
                $profileIds = $this->getProfileCollectionInstance()
                    ->getProfileCollectionForStore($store->getId())
                    ->getColumnValues('id');
                foreach (array_chunk($profileIds, self::QUERY_LIMIT) as $filterChunk) {
                    $this->aggregateProfileDataForStore($store, $service, $filterChunk);
                }
            }
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $service
     * @param array $profileIds
     *
     * @return void
     *
     * @throws LocalizedException
     */
    private function aggregateProfileDataForStore(StoreInterface $store, BaseService $service, array $profileIds): void
    {
        $select = $this->getConnection()
            ->select()
            ->from(
                [ProfileService::TYPE_CUSTOMER => $this->getCustomerCollectionInstance()->getMainTable()],
                ['profile_data' => $this->getSqlExpression($this->assembleProfileDataQuery($store, $service))]
            )
            ->where('customer.store_id = ?', $store->getId())
            ->where('customer.entity_id = profile.customer_id')
            ->where('profile.id IN (?)', $profileIds);
        $crossUpdateSqlQuery = $select->crossUpdateFromSelect(['profile' => $this->getMainTable()]);
        $this->getConnection()->query($crossUpdateSqlQuery);
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $service
     *
     * @return string
     *
     * @throws LocalizedException
     */
    public function assembleProfileDataQuery(StoreInterface $store, BaseService $service): string
    {
        $collection = $this->getCustomerCollectionInstance()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('store_id', $store->getId());

        $this->joinTablesOnCollection($collection);
        $this->addEavAttributes($collection, $store->getId());
        $this->addAdditionalColumns($collection, $service, $store->getId());

        $collection->getSelect()->group(['e.entity_id', 'subscriber.subscriber_id']);
        return $this->getJsonRepresentationFromSqlSelect($collection->getSelect()->assemble());
    }

    /**
     * @param CustomerCollection $collection
     *
     * @return void
     */
    private function joinTablesOnCollection(CustomerCollection $collection): void
    {
        $salesOrderGrid = $this->getTable('sales_order_grid');
        $collection->getSelect()
            ->joinLeft(
                ['cv' => $this->getTable('customer_visitor')],
                'e.entity_id = cv.customer_id',
                [
                    'last_visit_at' => '(
                        SELECT last_visit_at
                        ORDER BY visitor_id DESC
                    )'
                ]
            )
            ->joinLeft(
                ['group' => $this->getTable('customer_group')],
                'e.group_id = group.customer_group_id',
                ['customer_group_id', 'customer_group_code']
            )
            ->joinLeft(
                ['store' => $this->getTable('store')],
                'e.store_id = store.store_id',
                ['store_name' => 'name']
            )
            ->joinLeft(
                [ProfileService::TYPE_SUBSCRIBER => $this->getTable('newsletter_subscriber')],
                'e.entity_id = subscriber.customer_id',
                [
                    'subscribed' => $this->getSqlExpression(
                        sprintf(
                            '(
                                SELECT CASE
                                    WHEN subscriber_status = %s THEN true
                                    WHEN subscriber_status = %s THEN false
                                    ELSE null
                                END
                            )',
                            Subscriber::STATUS_SUBSCRIBED,
                            Subscriber::STATUS_UNSUBSCRIBED
                        )
                    )
                ]
            )
            ->joinLeft(
                ['sales' => $this->getTable('sales_order')],
                'e.entity_id = sales.customer_id',
                [
                    'total_orders_value' => 'SUM(sales.grand_total)',
                    'average_order_value' => 'AVG(sales.grand_total)',
                    'total_orders' => 'COUNT(*)',
                    'most_used_shipping_method' => '(
                        SELECT sales.shipping_description
                        GROUP BY shipping_method
                        ORDER BY COUNT(sales.shipping_description) DESC
                    )'
                ]
            )
            ->joinLeft(
                ['sog' => $this->getTable('sales_order_grid')],
                'e.entity_id = sog.customer_id',
                [
                    'last_order_date' => '(
                        SELECT sog.created_at
                        ORDER BY sog.created_at DESC
                    )',
                    'last_order_value' => '(
                        SELECT sog.grand_total
                        ORDER BY sog.created_at DESC
                    )'
                ]
            )
            ->joinLeft(
                ['cl' => $this->getTable('customer_log')],
                'e.entity_id = cl.customer_id',
                [
                    'last_login_date' => '(
                        SELECT cl.last_login_at
                        ORDER BY cl.log_id DESC
                    )',
                    'last_logout_at' => '(
                        SELECT cl.last_logout_at
                        ORDER BY cl.log_id DESC
                    )'
                ]
            )
            ->joinLeft(
                ['quote' => $this->getTable('quote')],
                'e.entity_id = quote.customer_id AND quote.is_active = 1',
                [
                    'active_shopping_cart' => 'quote.is_active',
                    'shopping_cart_created_at' => 'quote.created_at',
                    'shopping_cart_modified_at' => 'quote.updated_at',
                    'products_in_shopping_cart' => 'quote.items_count',
                ]
            )
            ->joinLeft(
                ['aov' => $this->getTable('eav_attribute_option_value')],
                'aov.option_id = e.gender AND aov.store_id = 0',
                ['gender_text' => 'aov.value']
            );
    }

    /**
     * @param CustomerCollection $collection
     * @param int $storeId
     *
     * @return void
     *
     * @throws LocalizedException
     */
    private function addEavAttributes(CustomerCollection $collection, int $storeId): void
    {
        $addressAttributes = [
            'billing_street' => 'customer_address/street',
            'billing_city' => 'customer_address/city',
            'billing_country_code' => 'customer_address/country_id',
            'billing_postcode' => 'customer_address/postcode',
            'billing_telephone' => 'customer_address/telephone',
            'billing_region' => 'customer_address/region'
        ];
        foreach ($addressAttributes as $alias => $attribute) {
            $collection->joinAttribute(
                $alias,
                $attribute,
                'default_billing',
                null,
                'left',
                $storeId
            );
        }
    }

    /**
     * @param CustomerCollection $collection
     * @param BaseService $service
     * @param int $storeId
     *
     * @return void
     */
    private function addAdditionalColumns(CustomerCollection $collection, BaseService $service, int $storeId): void
    {
        $collection->getSelect()->columns([
            'most_used_payment_method' => $this->getSqlExpression(
                sprintf(
                    '(
                        SELECT op.additional_information
                        FROM %s so
                        LEFT JOIN %s op ON op.parent_id = so.entity_id
                        WHERE so.customer_id = e.entity_id
                        GROUP BY op.method
                        ORDER BY COUNT(op.additional_information) DESC
                        LIMIT 1
                    )',
                    $collection->getTable('sales_order'),
                    $collection->getTable('sales_order_payment')
                )
            ),
            'last_product_wished_at' => $this->getSqlExpression(
                sprintf(
                    '(
                        SELECT wi.added_at
                        FROM %s w
                        LEFT JOIN %s wi ON wi.wishlist_id = w.wishlist_id
                        WHERE w.customer_id = e.entity_id
                        ORDER BY wi.added_at DESC
                        LIMIT 1
                    )',
                    $collection->getTable('wishlist'),
                    $collection->getTable('wishlist_item')
                )
            ),
            'total_products_in_wishlist' => $this->getSqlExpression(
                sprintf(
                    '(
                        SELECT COUNT(*)
                        FROM %s w
                        LEFT JOIN %s wi ON wi.wishlist_id = w.wishlist_id
                        WHERE w.customer_id = e.entity_id
                        LIMIT 1
                    )',
                    $collection->getTable('wishlist'),
                    $collection->getTable('wishlist_item')
                )
            ),
            'last_product_review_date' => $this->getSqlExpression(
                sprintf(
                    '(
                        SELECT r.created_at
                        FROM %s r
                        LEFT JOIN %s rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        ORDER BY r.created_at DESC
                        LIMIT 1
                    )',
                    $this->getTable('review'),
                    $this->getTable('review_detail')
                )
            ),
            'total_product_reviews' => $this->getSqlExpression(
                sprintf(
                    '(
                        SELECT COUNT(*)
                        FROM %s r
                        LEFT JOIN %s rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        GROUP BY customer_id
                        LIMIT 1
                    )',
                    $this->getTable('review'),
                    $this->getTable('review_detail')
                )
            ),
            'shop_currency' => $this->getSqlExpression(
                sprintf('(SELECT "%s")', $service->getStoreCurrency($storeId))
            )
        ]);
    }

    /**
     * @param string $sqlSelect
     *
     * @return string
     */
    private function getJsonRepresentationFromSqlSelect(string $sqlSelect): string
    {
        return sprintf(
            "(
                SELECT
                    JSON_OBJECT(
                        'email', email,
                        'shop_id', store_id,
                        'shop_name', store_name,
                        'subscribed', subscribed,
                        'date_of_birth', dob,
                        'gender', gender_text,
                        'created_at', created_at,
                        'last_login_date', last_login_date,
                        'last_logout_at', last_logout_at,
                        'last_visit_at', last_visit_at,
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
                        'last_order_date', last_order_date,
                        'most_used_shipping_method', most_used_shipping_method,
                        'most_used_payment_method', most_used_payment_method,
                        'active_shopping_cart', active_shopping_cart,
                        'shopping_cart_created_at', shopping_cart_created_at,
                        'shopping_cart_modified_at', shopping_cart_modified_at,
                        'products_in_shopping_cart', products_in_shopping_cart,
                        'last_product_wished_at', last_product_wished_at,
                        'total_products_in_wishlist', total_products_in_wishlist
                    )
                FROM (%s) AS q
                WHERE q.entity_id = customer.entity_id
            )",
            $sqlSelect
        );
    }
}
