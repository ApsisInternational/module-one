<?php

namespace Apsis\One\Controller\Api\Profiles;

use Apsis\One\Controller\Api\AbstractProfile;
use Magento\Framework\App\ResponseInterface;
use Throwable;

class Index extends AbstractProfile
{
    /**
     * Profile schema, see function getExpressionString class \Apsis\One\Model\ResourceModel\ProfileResource
     * IMPORTANT. Schema needs to match with \Apsis\One\Model\ResourceModel\ProfileResource::getExpressionString
     */
    const SCHEMA = [
        'profile_id' => ['code_name' => 'profile_id', 'type' => 'string', 'display_name' => 'Profile Id'],
        'email' => ['code_name' => 'email', 'type' => 'string', 'display_name' => 'Email'],
        'title' => ['code_name' => 'title', 'type' => 'string', 'display_name' => 'Title'],
        'first_name' => ['code_name' => 'first_name', 'type' => 'string', 'display_name' => 'First Name'],
        'middle_name' => ['code_name' => 'middle_name', 'type' => 'string', 'display_name' => 'Middle Name'],
        'last_name' => ['code_name' => 'last_name', 'type' => 'string', 'display_name' => 'Last Name'],
        'date_of_birth' => ['code_name' => 'date_of_birth', 'type' => 'integer', 'display_name' => 'Date Of Birth'],
        'gender' => ['code_name' => 'gender', 'type' => 'string', 'display_name' => 'Gender'],
        'street' => ['code_name' => 'street', 'type' => 'string', 'display_name' => 'Street'],
        'postcode' => ['code_name' => 'postcode', 'type' => 'string', 'display_name' => 'Postcode'],
        'city' => ['code_name' => 'city', 'type' => 'string', 'display_name' => 'City'],
        'region' => ['code_name' => 'region', 'type' => 'string', 'display_name' => 'Region'],
        'country' => ['code_name' => 'country', 'type' => 'string', 'display_name' => 'Country'],
        'phone' => ['code_name' => 'phone', 'type' => 'integer', 'display_name' => 'Phone'],
        'created_at' => ['code_name' => 'created_at', 'type' => 'integer', 'display_name' => 'Created At'],
        'last_login_date' => ['code_name' => 'last_login_date', 'type' => 'integer', 'display_name'=>'Last Login Date'],
        'subscribed' => ['code_name' => 'subscribed', 'type' => 'boolean', 'display_name' => 'Subscribed'],
        'list_id' => ['code_name' => 'list_id', 'type' => 'string', 'display_name' => 'List Id'],
        'list_name' => ['code_name' => 'list_name', 'type' => 'string', 'display_name' => 'List Name'],
        'shop_id' => ['code_name' => 'shop_id', 'type' => 'string', 'display_name' => 'Shop Id'],
        'shop_name' => ['code_name' => 'shop_name', 'type' => 'string', 'display_name' => 'Shop Name'],
        'shop_currency' => ['code_name' => 'shop_currency', 'type' => 'string', 'display_name' => 'Shop Currency'],
        'total_product_reviews' =>
            ['code_name' => 'total_product_reviews', 'type' => 'integer', 'display_name' => 'Total Product Reviews'],
        'last_product_review_date' =>
            ['code_name' => 'last_product_review_date', 'type' => 'integer', 'display_name' => 'Last Review Date'],
        'total_orders' => ['code_name' => 'total_orders', 'type' => 'integer', 'display_name' => 'Total Orders'],
        'total_orders_value' =>
            ['code_name' => 'total_orders_value', 'type' => 'double', 'display_name' => 'Total Orders Value'],
        'average_order_value' =>
            ['code_name' => 'average_order_value', 'type' => 'double', 'display_name' => 'Average Order Value'],
        'last_order_date' =>
            ['code_name' => 'last_order_date', 'type' => 'integer', 'display_name' => 'Last Order Date'],
        'last_order_value' =>
            ['code_name' => 'last_order_value', 'type' => 'double', 'display_name' => 'Last Order Value']
    ];
    const DATETIME_FIELDS = [
        'last_order_date',
        'last_product_review_date',
        'last_login_date',
        'date_of_birth',
        'created_at'
    ];
    const EXCLUDE_CUSTOMER_ATTRIBUTES = [
        'prefix',
        'firstname',
        'middlename',
        'lastname',
        'dob',
        'group_id',
        'store_id',
        'password_hash',
        'rp_token',
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
        try {
            return $this->sendResponse(200, null, json_encode([self::ENTITY_NAME]));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileSchema(): ResponseInterface
    {
        try {
            $schema = array_merge(array_values(self::SCHEMA), array_values($this->getCustomerAttributes()));
            return $this->sendResponse(200, null, json_encode($schema));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileRecords(): ResponseInterface
    {
        try {
            $records = $this->getProfiles();
            if (is_int($records)) {
                return $this->sendErrorInResponse($records);
            }
            return $this->sendResponse(200, null, json_encode($records));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileRecordsCount(): ResponseInterface
    {
        try {
            $count = $this->getProfilesCount();
            if (is_int($count)) {
                return $this->sendErrorInResponse($count);
            }
            return $this->sendResponse(200, null, json_encode($count));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
