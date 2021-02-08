<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\Profile;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    /**
     * Accounts section
     */
    /** OAUTH */
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_GROUP = 'apsis_one_accounts/oauth';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED = 'apsis_one_accounts/oauth/enabled';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ID = 'apsis_one_accounts/oauth/id';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET = 'apsis_one_accounts/oauth/secret';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_REGION = 'apsis_one_accounts/oauth/region';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN = 'apsis_one_accounts/oauth/token';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE = 'apsis_one_accounts/oauth/token_expire';
    const CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY = 'apsis_one_accounts/subscription/key';

    /**
     * Data mapping section
     */
    /** Section & data mappings */
    const CONFIG_APSIS_ONE_MAPPINGS_SECTION_GROUP = 'apsis_one_mappings/section_mapping';
    const CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION = 'apsis_one_mappings/section_mapping/section';
    /** Customer/Subscriber common attributes */
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_GROUP
        = 'apsis_one_mappings/customer_subscriber_common_attribute';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_PROFILE_KEY
        = 'apsis_one_mappings/customer_subscriber_common_attribute/profile_key';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_WEBSITE_ID
        = 'apsis_one_mappings/customer_subscriber_common_attribute/website_id';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_STORE_ID
        = 'apsis_one_mappings/customer_subscriber_common_attribute/store_id';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_WEBSITE_NAME
        = 'apsis_one_mappings/customer_subscriber_common_attribute/website_name';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_STORE_NAME
        = 'apsis_one_mappings/customer_subscriber_common_attribute/store_name';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
        = 'apsis_one_mappings/customer_subscriber_common_attribute/email';
    /** Subscriber attributes */
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_GROUP = 'apsis_one_mappings/subscriber_attribute';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_ID = 'apsis_one_mappings/subscriber_attribute/subscriber_id';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_STATUS = 'apsis_one_mappings/subscriber_attribute/subscriber_status';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_STATUS_CHANGE_AT
        = 'apsis_one_mappings/subscriber_attribute/change_status_at';
    /** Customer attributes */
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_GROUP = 'apsis_one_mappings/customer_attribute';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TITLE = 'apsis_one_mappings/customer_attribute/title';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_ID = 'apsis_one_mappings/customer_attribute/customer_id';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_FIRST_NAME = 'apsis_one_mappings/customer_attribute/first_name';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_NAME = 'apsis_one_mappings/customer_attribute/last_name';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DOB = 'apsis_one_mappings/customer_attribute/dob';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_GENDER = 'apsis_one_mappings/customer_attribute/gender';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_CREATED_AT = 'apsis_one_mappings/customer_attribute/created_at';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_LOGGED_IN_DATE
        = 'apsis_one_mappings/customer_attribute/last_logged_date';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_CUSTOMER_GROUP = 'apsis_one_mappings/customer_attribute/customer_group';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_REVIEW_COUNT = 'apsis_one_mappings/customer_attribute/review_count';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_REVIEW_DATE
        = 'apsis_one_mappings/customer_attribute/last_review_date';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_ADDRESS_LINE_1
        = 'apsis_one_mappings/customer_attribute/billing_address_1';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_ADDRESS_LINE_2
        = 'apsis_one_mappings/customer_attribute/billing_address_2';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_CITY = 'apsis_one_mappings/customer_attribute/billing_city';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_STATE = 'apsis_one_mappings/customer_attribute/billing_state';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_COUNTRY = 'apsis_one_mappings/customer_attribute/billing_country';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_POSTCODE
        = 'apsis_one_mappings/customer_attribute/billing_postcode';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_TELEPHONE
        = 'apsis_one_mappings/customer_attribute/billing_telephone';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_COMPANY = 'apsis_one_mappings/customer_attribute/billing_company';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_ADDRESS_1
        = 'apsis_one_mappings/customer_attribute/delivery_address_1';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_ADDRESS_2
        = 'apsis_one_mappings/customer_attribute/delivery_address_2';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_CITY = 'apsis_one_mappings/customer_attribute/delivery_city';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_STATE = 'apsis_one_mappings/customer_attribute/delivery_state';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_COUNTRY
        = 'apsis_one_mappings/customer_attribute/delivery_country';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_POSTCODE
        = 'apsis_one_mappings/customer_attribute/delivery_postcode';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_TELEPHONE
        = 'apsis_one_mappings/customer_attribute/delivery_telephone';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_COMPANY
        = 'apsis_one_mappings/customer_attribute/delivery_company';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_PURCHASE_DATE
        = 'apsis_one_mappings/customer_attribute/last_order_date';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TOTAL_NUMBER_OF_ORDERS
        = 'apsis_one_mappings/customer_attribute/number_of_orders';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_AVERAGE_ORDER_VALUE
        = 'apsis_one_mappings/customer_attribute/average_order_value';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TOTAL_SPEND = 'apsis_one_mappings/customer_attribute/total_spend';

    /**
     * Sync settings
     */
    const CONFIG_APSIS_ONE_SYNC_SETTING_GROUP = 'apsis_one_sync/sync';
    const CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED = 'apsis_one_sync/sync/subscriber_enabled';
    const CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC = 'apsis_one_sync/sync/subscriber_consent_topic';
    const CONFIG_APSIS_ONE_SYNC_SETTING_ADDITIONAL_TOPIC = 'apsis_one_sync/sync/additional_consent_topic';
    const CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED = 'apsis_one_sync/sync/customer_enabled';

    /**
     * Events section
     */
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_GROUP = 'apsis_one_events/events';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_LOGIN = 'apsis_one_events/events/login';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_ORDER = 'apsis_one_events/events/order';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_REVIEW = 'apsis_one_events/events/review';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_WISHLIST = 'apsis_one_events/events/wishlist';
    const CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_UNSUBSCRIBE = 'apsis_one_events/events/unsubscribe';
    const CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_2_CUSTOMER = 'apsis_one_events/events/subscriber_2_customer';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_2_SUBSCRIBER = 'apsis_one_events/events/customer_2_subscriber';
    const CONFIG_APSIS_ONE_EVENTS_PRODUCT_CARTED = 'apsis_one_events/events/product_carted';
    const CONFIG_APSIS_ONE_EVENTS_ORDER_HISTORY_DONE_FLAG = 'apsis_one_events/events/order_history_done_flag';
    const CONFIG_APSIS_ONE_EVENTS_QUOTE_HISTORY_DONE_FLAG = 'apsis_one_events/events/quote_history_done_flag';
    const CONFIG_APSIS_ONE_EVENTS_REVIEW_HISTORY_DONE_FLAG = 'apsis_one_events/events/review_history_done_flag';
    const CONFIG_APSIS_ONE_EVENTS_WISHLIST_HISTORY_DONE_FLAG = 'apsis_one_events/events/wishlist_history_done_flag';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_EVENTS_DURATION =
        'apsis_one_events/events/order_historical_event_duration';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_EVENTS_DURATION =
        'apsis_one_events/events/cart_historical_event_duration';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_EVENTS_DURATION =
        'apsis_one_events/events/review_historical_event_duration';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_EVENTS_DURATION =
        'apsis_one_events/events/wishlist_historical_event_duration';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_DURATION_TIMESTAMP =
        'apsis_one_events/events/cart_event_duration_timestamp';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_DURATION_TIMESTAMP =
        'apsis_one_events/events/order_event_duration_timestamp';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_DURATION_TIMESTAMP =
        'apsis_one_events/events/review_event_duration_timestamp';
    const CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_DURATION_TIMESTAMP =
        'apsis_one_events/events/wishlist_event_duration_timestamp';
    const CONFIG_APSIS_ONE_EVENTS_REGISTER_ABANDONED_CART_AFTER_DURATION =
        'apsis_one_events/events/abandoned_cart_duration_after';

    /**
     * Configuration section
     */
    /** Profile Sync */
    const CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_SECTION = 'apsis_one_configuration/profile_sync';
    const CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_ORDER_STATUSES
        = 'apsis_one_configuration/profile_sync/order_status';
    const CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_SUBSCRIBER_BATCH_SIZE
        = 'apsis_one_configuration/profile_sync/subscriber_batch_size';
    const CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_CUSTOMER_BATCH_SIZE
        = 'apsis_one_configuration/profile_sync/customer_batch_size';
    /** Developer settings */
    const CONFIG_APSIS_ONE_CONFIGURATION_DEVELOPER_SETTING_SECTION = 'apsis_one_configuration/developer_settings';
    const CONFIG_APSIS_ONE_CONFIGURATION_DEVELOPER_SETTING_CLEANUP_AFTER =
        'apsis_one_configuration/developer_settings/cleanup_after';
    /** Tracking Script */
    const CONFIG_APSIS_ONE_CONFIGURATION_TRACKING_SECTION = 'apsis_one_configuration/tracking';
    const CONFIG_APSIS_ONE_CONFIGURATION_TRACKING_ENABLED = 'apsis_one_configuration/tracking/enabled';
    const CONFIG_APSIS_ONE_CONFIGURATION_TRACKING_SCRIPT = 'apsis_one_configuration/tracking/script';

    const SUBSCRIBER_ATTRIBUTE_LIST = [
        self::CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_ID,
        self::CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_STATUS,
        self::CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_STATUS_CHANGE_AT
    ];
    const CUSTOMER_ATTRIBUTE_LIST = [
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_ADDRESS_LINE_1,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_ADDRESS_LINE_2,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_CITY,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_COMPANY,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_COUNTRY,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_POSTCODE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_STATE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_BILLING_TELEPHONE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_ADDRESS_1,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_ADDRESS_2,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_CITY,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_COMPANY,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_COUNTRY,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_POSTCODE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_STATE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DELIVERY_TELEPHONE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_CREATED_AT,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_CUSTOMER_GROUP,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_LOGGED_IN_DATE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_NAME,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_PURCHASE_DATE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_REVIEW_DATE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TITLE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_AVERAGE_ORDER_VALUE,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_ID,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_GENDER,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TOTAL_NUMBER_OF_ORDERS,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TOTAL_SPEND,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_REVIEW_COUNT,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_DOB,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_FIRST_NAME
    ];
    const COMMON_ATTRIBUTE_LIST = [
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_STORE_ID,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_STORE_NAME,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_WEBSITE_ID,
        self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_WEBSITE_NAME
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param StoreInterface $store
     * @return array
     */
    public function getSubscriberAttributeMapping(StoreInterface $store)
    {
        $subscriberMapping = $this->getConfigMappingsByPath($store, self::CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_GROUP);
        $commonMapping = $this->getCommonAttributeMapping($store);
        return array_merge($subscriberMapping, $commonMapping);
    }

    /**
     * @param StoreInterface $store
     * @return array
     */
    public function getCustomerAttributeMapping(StoreInterface $store)
    {
        $customerMapping = $this->getConfigMappingsByPath($store, self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_GROUP);
        $commonMapping = $this->getCommonAttributeMapping($store);
        return array_merge($customerMapping, $commonMapping);
    }

    /**
     * @param StoreInterface $store
     * @return array
     */
    private function getCommonAttributeMapping(StoreInterface $store)
    {
        return $this->getConfigMappingsByPath($store, self::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_GROUP);
    }

    /**
     * @param StoreInterface $store
     * @param string $path
     * @return array
     */
    private function getConfigMappingsByPath(StoreInterface $store, $path)
    {
        $mapping = $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        //skip non mapped config
        foreach ($mapping as $key => $value) {
            if (! $value) {
                unset($mapping[$key]);
            }
        }

        return $mapping;
    }

    /**
     * @param string $keySpaceDiscriminator
     * @param array $mappings
     * @param array $attributesArrWithVersionId
     * @param array $topicsMapping
     * @param string $consentType
     *
     * @return array
     */
    public function getJsonMappingData(
        string $keySpaceDiscriminator,
        array $mappings,
        array $attributesArrWithVersionId,
        array $topicsMapping = [],
        string $consentType = ''
    ) {
        $attributeMappings = [];
        foreach ($mappings as $key => $mapping) {
            if (isset($attributesArrWithVersionId[$mapping])) {
                $attributeMappings[] = [
                    'field_selector' => $key,
                    'attribute_version_id' => $attributesArrWithVersionId[$mapping]
                ];
            }
        }

        $jsonMappingData = [
            'keyspace_mappings' => [
                [
                    'keyspace_discriminator' => $keySpaceDiscriminator,
                    'field_selector' => Profile::INTEGRATION_KEYSPACE,
                    'merge_profiles' => true
                ],
                [
                    'keyspace_discriminator' => Profile::EMAIL_KEYSPACE_DISCRIMINATOR,
                    'field_selector' => Profile::EMAIL_FIELD,
                    'merge_profiles' => true
                ]
            ],
            'options' => ['update_existing_profiles' => true, 'clear_existing_attributes' => true],
            'attribute_mappings' => $attributeMappings
        ];

        if (! empty($topicsMapping) && strlen($consentType)) {
            $consents = [];
            foreach ($topicsMapping as $topicDiscriminator => $consentListDiscriminator) {
                $consents[] = [
                    'resubscribe_if_opted_out' => true,
                    'field_selector' => $topicDiscriminator,
                    'channel_discriminator' => Profile::EMAIL_CHANNEL_DISCRIMINATOR,
                    'consent_list_discriminator' => $consentListDiscriminator,
                    'topic_discriminator' => $topicDiscriminator,
                    'type' => $consentType
                ];
            }
            $jsonMappingData['consent_mappings'] = [
                ['address_field_selector' => Profile::EMAIL_FIELD, 'consents' => $consents]
            ];
        }

        return $jsonMappingData;
    }
}
