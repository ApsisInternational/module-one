<?php

namespace Apsis\One\Helper;

use Apsis\One\Helper\Core as ApsisCoreHelper;

class Config extends ApsisCoreHelper
{
    /**
     * Accounts section
     */
    /** OAUTH */
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_GROUP = 'apsis_one_accounts/oauth';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED = 'apsis_one_accounts/oauth/enabled';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ID = 'apsis_one_accounts/oauth/id';
    const CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET = 'apsis_one_accounts/oauth/secret';

    /**
     * Data mapping section
     */
    /** Section & topic */
    const CONFIG_APSIS_ONE_MAPPINGS_SECTION_TOPIC_GROUP = 'apsis_one_mappings/section_topic_mapping';
    const CONFIG_APSIS_ONE_MAPPINGS_SECTION_TOPIC_SECTION = 'apsis_one_mappings/section_topic_mapping/section';
    const CONFIG_APSIS_ONE_MAPPINGS_SECTION_TOPIC_TOPIC = 'apsis_one_mappings/section_topic_mapping/topic';
    /** Subscriber attributes */
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_GROUP = 'apsis_one_mappings/subscriber_attribute';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_ID = 'apsis_one_mappings/subscriber_attribute/subscriber_id';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_EMAIL = 'apsis_one_mappings/subscriber_attribute/subscriber_email';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_STATUS = 'apsis_one_mappings/subscriber_attribute/subscriber_status';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_WEBSITE_ID = 'apsis_one_mappings/subscriber_attribute/website_id';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_STORE_ID = 'apsis_one_mappings/subscriber_attribute/store_id';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_WEBSITE_NAME = 'apsis_one_mappings/subscriber_attribute/website_name';
    const CONFIG_APSIS_ONE_MAPPINGS_SUBSCRIBER_STORE_NAME = 'apsis_one_mappings/subscriber_attribute/store_name';
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
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_QUOTE_ID = 'apsis_one_mappings/customer_attribute/last_quote_id';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_INCREMENT_ID
        = 'apsis_one_mappings/customer_attribute/last_increment_id';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_PURCHASE_DATE
        = 'apsis_one_mappings/customer_attribute/last_order_date';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TOTAL_NUMBER_OF_ORDERS
        = 'apsis_one_mappings/customer_attribute/number_of_orders';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_AVERAGE_ORDER_VALUE
        = 'apsis_one_mappings/customer_attribute/average_order_value';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_TOTAL_SPEND = 'apsis_one_mappings/customer_attribute/total_spend';
    const CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_AC_TOKEN = 'apsis_one_mappings/customer_attribute/ac_token';

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
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_CART = 'apsis_one_events/events/cart';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_REVIEW = 'apsis_one_events/events/review';
    const CONFIG_APSIS_ONE_EVENTS_CUSTOMER_WISHLIST = 'apsis_one_events/events/wishlist';
    const CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_UNSUBSCRIBE = 'apsis_one_events/events/unsubscribe';
    const CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_2_CUSTOMER = 'apsis_one_events/events/subscriber_2_customer';
    const CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_NOT_A_CUSTOMER = 'apsis_one_events/events/subscriber_not_a_customer';
}
