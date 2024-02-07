<?php

namespace Apsis\One\Controller\Api\Carts;

use Apsis\One\Controller\Api\AbstractEcommerce;

class Items extends AbstractEcommerce
{
    const SCHEMA = [
        ['code_name' => 'product_id', 'type' => 'string', 'display_name' => 'Product Id'],
        ['code_name' => 'cart_id', 'type' => 'string', 'display_name' => 'Cart Id'],
        ['code_name' => 'sku', 'type' => 'string', 'display_name' => 'Product Sku'],
        ['code_name' => 'name', 'type' => 'string', 'display_name' => 'Product Name'],
        ['code_name' => 'product_image_url', 'type' => 'string', 'display_name' => 'Product Image Url'],
        ['code_name' => 'product_url', 'type' => 'string', 'display_name' => 'Product Url'],
        ['code_name' => 'qty_ordered', 'type' => 'double', 'display_name' => 'Product Quantity'],
        ['code_name' => 'price_amount', 'type' => 'double', 'display_name' => 'Product Price'],
        ['code_name' => 'tax_amount', 'type' => 'double', 'display_name' => 'Tax Amount'],
        ['code_name' => 'discount_amount', 'type' => 'double', 'display_name' => 'Discount Amount'],
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getSchema' => ['query' => []],
        'getItems' => [
            'query' => [
                'page_size' => 'int',
                'page' => 'int',
                'cart_ids' => 'string'
            ]
        ]
    ];
}
