<?php

namespace Apsis\One\Controller\Api\Profiles;

use Apsis\One\Controller\Api\AbstractProfile;
use Magento\Framework\App\ResponseInterface;

class Index extends AbstractProfile
{
    // Profile schema, see function getExpressionString in class Apsis\One\Model\ResourceModel\Profile
    const SCHEMA = [
        'profile_id' => ['code_name' => 'profile_id', 'type' => 'integer', 'display_name' => 'Profile Id'],
        'website_id' => ['code_name' => 'website_id', 'type' => 'integer', 'display_name' => 'Website Id'],
        'store_id' => ['code_name' => 'store_id', 'type' => 'integer', 'display_name' => 'Store Id'],
        'website_name' => ['code_name' => 'website_name', 'type' => 'string', 'display_name' => 'Website Name'],
        'store_name' => ['code_name' => 'store_name', 'type' => 'string', 'display_name' => 'Store Name'],
        'email' => ['code_name' => 'email', 'type' => 'string', 'display_name' => 'Email'],
        'subscriber_id' => ['code_name' => 'subscriber_id', 'type' => 'integer', 'display_name' => 'Subscriber Id'],
        'subscriber_status' =>
            ['code_name' => 'subscriber_status', 'type' => 'string', 'display_name' => 'Subscription Status'],
        'change_status_at' =>
            ['code_name' => 'change_status_at', 'type' => 'integer', 'display_name' => 'Subscription Changed At'],
        'title' => ['code_name' => 'title', 'type' => 'string', 'display_name' => 'Title'],
        'customer_id' => ['code_name' => 'customer_id', 'type' => 'integer', 'display_name' => 'Customer Id'],
        'firstname' => ['code_name' => 'firstname', 'type' => 'string', 'display_name' => 'First Name'],
        'lastname' => ['code_name' => 'lastname', 'type' => 'string', 'display_name' => 'Last Name'],
        'dob' => ['code_name' => 'dob', 'type' => 'integer', 'display_name' => 'Date Of Birth'],
        'gender' => ['code_name' => 'gender', 'type' => 'string', 'display_name' => 'Gender'],
        'created_at' => ['code_name' => 'created_at', 'type' => 'integer', 'display_name' => 'Created At'],
        'last_logged_date' =>
            ['code_name' => 'last_logged_date', 'type' => 'integer', 'display_name' => 'Last Logged In Date'],
        'customer_group' => ['code_name' => 'customer_group', 'type' => 'string', 'display_name' => 'Customer Group'],
        'review_count' => ['code_name' => 'review_count', 'type' => 'integer', 'display_name' => 'Total Reviews'],
        'last_review_date' =>
            ['code_name' => 'last_review_date', 'type' => 'integer', 'display_name' => 'Last Review Date'],
        'billing_street' => ['code_name' => 'billing_street', 'type' => 'string', 'display_name' => 'Billing Street'],
        'billing_state' => ['code_name' => 'billing_state', 'type' => 'string', 'display_name' => 'Billing State'],
        'billing_city' => ['code_name' => 'billing_city', 'type' => 'string', 'display_name' => 'Billing City'],
        'billing_country' =>
            ['code_name' => 'billing_country', 'type' => 'string', 'display_name' => 'Billing Country'],
        'billing_postcode' =>
            ['code_name' => 'billing_postcode', 'type' => 'string', 'display_name' => 'Billing Postcode'],
        'billing_telephone' =>
            ['code_name' => 'billing_telephone', 'type' => 'integer', 'display_name' => 'Billing Telephone'],
        'billing_company' =>
            ['code_name' => 'billing_company', 'type' => 'string', 'display_name' => 'Billing Company'],
        'delivery_street' =>
            ['code_name' => 'delivery_street', 'type' => 'string', 'display_name' => 'Delivery Street'],
        'delivery_city' => ['code_name' => 'delivery_city', 'type' => 'string', 'display_name' => 'Delivery City'],
        'delivery_state' => ['code_name' => 'delivery_state', 'type' => 'string', 'display_name' => 'Delivery State'],
        'delivery_country' =>
            ['code_name' => 'delivery_country', 'type' => 'string', 'display_name' => 'Delivery Country'],
        'delivery_postcode' =>
            ['code_name' => 'delivery_postcode', 'type' => 'string', 'display_name' => 'Delivery Postcode'],
        'delivery_telephone' =>
            ['code_name' => 'delivery_telephone', 'type' => 'integer', 'display_name' => 'Delivery Telephone'],
        'delivery_company' =>
            ['code_name' => 'delivery_company', 'type' => 'string', 'display_name' => 'Delivery Company'],
        'last_order_date' =>
            ['code_name' => 'last_order_date', 'type' => 'integer', 'display_name' => 'Last Order Date'],
        'number_of_orders' =>
            ['code_name' => 'number_of_orders', 'type' => 'integer', 'display_name' => 'Total Orders'],
        'average_order_value' =>
            ['code_name' => 'average_order_value', 'type' => 'double', 'display_name' => 'Average Order Value'],
        'total_spend' => ['code_name' => 'total_spend', 'type' => 'double', 'display_name' => 'Total Spent']
    ];
    const DATETIME_FIELDS = [
        'last_order_date',
        'last_review_date',
        'last_logged_date',
        'dob',
        'created_at',
        'change_status_at'
    ];
    const PHONE_FIELDS = [
        'billing_telephone',
        'delivery_telephone'
    ];
    const EXCLUDE_CUSTOMER_ATTRIBUTES = [
        'created_in',
        'password_hash',
        'rp_token',
        'rp_token_create_at'
    ];
    const ENTITY_NAME = 'profile';

    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = [
        'ProfileEntities' => ['GET', 'HEAD'],
        'ProfileSchema' => ['GET', 'HEAD'],
        'ProfileRecords' => ['GET', 'HEAD'],
        'ProfileRecordsCount' => ['GET', 'HEAD']
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getProfileEntities' => ['query' => ['page_size' => 'int', 'page' => 'int']],
        'getProfileSchema' => ['query' => []],
        'getProfileRecords' => [
            'query' => ['page_size' => 'int', 'page' => 'int']
        ],
        'getProfileRecordsCount' => ['query' => []]
    ];

    /**
     * @return ResponseInterface
     */
    protected function getProfileEntities(): ResponseInterface
    {
        return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize([self::ENTITY_NAME]));
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileSchema(): ResponseInterface
    {
        $schema = array_merge(array_values(self::SCHEMA), array_values($this->getCustomerAttributes()));
        return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize($schema));
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileRecords(): ResponseInterface
    {
        $records = $this->getProfiles();
        if (is_int($records)) {
            return $this->sendErrorInResponse($records);
        }
        return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize($records));
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileRecordsCount(): ResponseInterface
    {
        $count = $this->getProfilesCount();
        if (is_int($count)) {
            return $this->sendErrorInResponse($count);
        }
        return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize($count));
    }
}
