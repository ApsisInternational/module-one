<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Newsletter\Model\Subscriber;

class SubscriberStatusSourceModel implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'N/A', 'label' => __('N/A')],
            ['value' => Subscriber::STATUS_SUBSCRIBED, 'label' => __('Subscribed')],
            ['value' => Subscriber::STATUS_NOT_ACTIVE, 'label' => __('Not Active')],
            ['value' => Subscriber::STATUS_UNSUBSCRIBED, 'label' => __('Unsubscribed')],
            ['value' => Subscriber::STATUS_UNCONFIRMED, 'label' => __('Unconfirmed')]
        ];
    }
}
