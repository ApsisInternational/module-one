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
                'value' => EventModel::EVENT_PRODUCT_REVIEWED,
                'label' => __('Product Reviewed')
            ],
            [
                'value' => EventModel::EVENT_PRODUCT_WISHED,
                'label' => __('Product Wished')
            ],
            [
                'value' => EventModel::EVENT_PRODUCT_CARTED,
                'label' => __('Product Carted')
            ],
            [
                'value' => EventModel::EVENT_CART_ABANDONED,
                'label' => __('Cart Abandoned')
            ],
            [
                'value' => EventModel::EVENT_PLACED_ORDER,
                'label' => __('Order Placed')
            ],
            [
                'value' => EventModel::EVENT_SUBSCRIPTION_CHANGED,
                'label' => __('Subscription Changed')
            ],
            [
                'value' => EventModel::EVENT_LOGGED_IN,
                'label' => __('Logged In')
            ],
        ];
    }
}
