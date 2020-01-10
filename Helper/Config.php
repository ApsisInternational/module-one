<?php

namespace Apsis\One\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    /**
     * Accounts section
     */
    /** OAUTH */
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_GROUP = 'apsis_one_accounts/oauth';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED = 'apsis_one_accounts/oauth/enabled';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ID = 'apsis_one_accounts/oauth/id';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET = 'apsis_one_accounts/oauth/secret';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN = 'apsis_one_accounts/oauth/token';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE = 'apsis_one_accounts/oauth/token_expire';

    /**
     * Data mapping section
     */
    /** Section & topic */
    const CONFIG_APSIS_ONE_MAPPINGS_SECTION_GROUP = 'apsis_one_mappings/section_mapping';
    const CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION = 'apsis_one_mappings/section_mapping/section';
    /** Customer/Subscriber common attributes */
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_GROUP
        = 'apsis_one_mappings/customer_subscriber_common_attribute';
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
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_AC_TOKEN = 'apsis_one_mappings/customer_attribute/ac_token';

    /**
     * Sync settings
     */
    const CONFIG_APSIS_ONE_SYNC_SETTING_GROUP = 'apsis_one_sync/sync';
    const CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED = 'apsis_one_sync/sync/subscriber_enabled';
    const CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC = 'apsis_one_sync/sync/subscriber_consent_topic';
    const CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED = 'apsis_one_sync/sync/customer_enabled';

    /**
     * Abandoned carts section
     */
    const CONFIG_APSIS_ONE_ABANDONED_CARTS_GROUP = 'apsis_one_abandoned/customers';
    const CONFIG_APSIS_ONE_ABANDONED_CARTS__URL = 'apsis_one_abandoned/customers/url';
    const CONFIG_APSIS_ONE_ABANDONED_CARTS_SEND_AFTER = 'apsis_one_abandoned/customers/send_after';

    /**
     * Customer events section
     */
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_GROUP = 'apsis_one_events/events';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_LOGIN = 'apsis_one_events/events/login';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_ORDER = 'apsis_one_events/events/order';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_REVIEW = 'apsis_one_events/events/review';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_WISHLIST = 'apsis_one_events/events/wishlist';
    const CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_UNSUBSCRIBE = 'apsis_one_events/events/unsubscribe';
    const CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_2_CUSTOMER = 'apsis_one_events/events/subscriber_2_customer';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_2_SUBSCRIBER = 'apsis_one_events/events/customer_2_subscriber';

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
        unset($customerMapping['ac_token']);
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
}
