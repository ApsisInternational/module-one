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
                'value' => Subscriber::STATUS_SUBSCRIBED,
                'label' => 'Subscribed'
            ],
            [
                'value' => Subscriber::STATUS_NOT_ACTIVE,
                'label' => 'Not Active',
            ],
            [
                'value' => Subscriber::STATUS_UNCONFIRMED,
                'label' => 'Unconfirmed',
            ],
            [
                'value' => Subscriber::STATUS_UNSUBSCRIBED,
                'label' => 'Unsubscribed',
            ]
        ];
    }
}
