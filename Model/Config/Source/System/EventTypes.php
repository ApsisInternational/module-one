<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\Event;

class EventTypes implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART,
                'label' => 'Customer Abandoned Cart'
            ],
            [
                'value' => Event::EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER,
                'label' => 'Subscriber Becomes Customer'
            ],
            [
                'value' => Event::EVENT_TYPE_SUBSCRIBER_NO_LONGER_CUSTOMER,
                'label' => 'Subscriber No Longer Customer'
            ],
            [
                'value' => Event::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE,
                'label' => 'Subscriber Unsubscribe'
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_LOGIN,
                'label' => 'Customer Login'
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_PLACED_ORDER,
                'label' => 'Customer Placed An Order'
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
                'label' => 'Customer Added Product To Cart'
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
                'label' => 'Customer Left Product Review'
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
                'label' => 'Customer Added Product To Wishlist'
            ],
        ];

        return $options;
    }
}
