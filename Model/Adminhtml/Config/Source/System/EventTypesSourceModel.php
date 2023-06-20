<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\EventModel;

class EventTypesSourceModel implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => EventModel::EVENT_TYPE_CUSTOMER_ABANDONED_CART,
                'label' => __('Customer Abandoned Cart')
            ],
            [
                'value' => EventModel::EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER,
                'label' => __('Subscriber Becomes Customer')
            ],
            [
                'value' => EventModel::EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER,
                'label' => __('Customer Becomes Subscriber')
            ],
            [
                'value' => EventModel::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE,
                'label' => __('Subscriber Unsubscribe')
            ],
            [
                'value' => EventModel::EVENT_TYPE_CUSTOMER_LOGIN,
                'label' => __('Customer Login')
            ],
            [
                'value' => EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
                'label' => __('Customer/Subscriber Placed An Order')
            ],
            [
                'value' => EventModel::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
                'label' => __('Customer Left Product Review')
            ],
            [
                'value' => EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
                'label' => __('Customer Added Product To Wishlist')
            ],
            [
                'value' => EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
                'label' => __('Customer Added Product To Cart')
            ]
        ];
    }
}
