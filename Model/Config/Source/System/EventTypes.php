<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\Event;

class EventTypes implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART,
                'label' => __('Customer Abandoned Cart')
            ],
            [
                'value' => Event::EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER,
                'label' => __('Subscriber Becomes Customer')
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER,
                'label' => __('Customer Becomes Subscriber')
            ],
            [
                'value' => Event::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE,
                'label' => __('Subscriber Unsubscribe')
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_LOGIN,
                'label' => __('Customer Login')
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
                'label' => __('Customer/Subscriber Placed An Order')
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
                'label' => __('Customer Left Product Review')
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
                'label' => __('Customer Added Product To Wishlist')
            ],
            [
                'value' => Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
                'label' => __('Customer Added Product To Cart')
            ]
        ];
    }
}
