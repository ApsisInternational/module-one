<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Newsletter\Model\Subscriber;

class SubscriberStatus implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => null,
                'label' => __('N/A')
            ],
            [
                'value' => Subscriber::STATUS_SUBSCRIBED,
                'label' => __('Subscribed')
            ],
            [
                'value' => Subscriber::STATUS_NOT_ACTIVE,
                'label' => __('Not Active')
            ],
            [
                'value' => Subscriber::STATUS_UNCONFIRMED,
                'label' => __('Unconfirmed')
            ],
            [
                'value' => Subscriber::STATUS_UNSUBSCRIBED,
                'label' => __('Unsubscribed')
            ]
        ];
    }
}
