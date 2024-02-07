<?php

namespace Apsis\One\Controller\Api\Carts;

use Apsis\One\Controller\Api\AbstractEcommerce;
use Apsis\One\Controller\Api\Profiles\Index as ProfileIndex;

class Index extends AbstractEcommerce
{
    const SCHEMA = [
        ['code_name' => 'cart_id', 'type' => 'string', 'display_name' => 'Cart Id'],
        ['code_name' => 'profile_id', 'type' => 'string', 'display_name' => 'Profile Id'],
        ['code_name' => 'created_at', 'type' => ProfileIndex::ENUM_UNIX_S, 'display_name' => 'Created Id'],
        ['code_name' => 'updated_at', 'type' => ProfileIndex::ENUM_UNIX_S, 'display_name' => 'Updated At'],
        ['code_name' => 'subtotal_amount', 'type' => 'double', 'display_name' => 'Subtotal'],
        ['code_name' => 'grand_total_amount', 'type' => 'double', 'display_name' => 'Grand Total'],
        ['code_name' => 'tax_amount', 'type' => 'double', 'display_name' => 'Tax Amount'],
        ['code_name' => 'shipping_amount', 'type' => 'double', 'display_name' => 'Shipping Amount'],
        ['code_name' => 'discount_amount', 'type' => 'double', 'display_name' => 'Discount Amount'],
        ['code_name' => 'payment_method_title', 'type' => 'string', 'display_name' => 'Payment Method'],
        ['code_name' => 'shipping_method_title', 'type' => 'string', 'display_name' => 'Shipping Method'],
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getSchema' => ['query' => []],
        'getItems' => [
            'query' => [
                'page_size' => 'int',
                'page' => 'int'
            ]
        ]
    ];
}
