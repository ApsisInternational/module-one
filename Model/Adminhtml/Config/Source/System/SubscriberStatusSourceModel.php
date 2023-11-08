<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Newsletter\Model\Subscriber;

class SubscriberStatusSourceModel extends AbstractOptionsSource
{
    /**
     * @inheritdoc
     */
    protected function getOptionTextMap(): array
    {
        return [
            'N/A' => 'N/A',
            Subscriber::STATUS_SUBSCRIBED => 'Subscribed',
            Subscriber::STATUS_NOT_ACTIVE => 'Not Active',
            Subscriber::STATUS_UNSUBSCRIBED => 'Unsubscribed',
            Subscriber::STATUS_UNCONFIRMED => 'Unconfirmed'
        ];
    }
}
