<?php

namespace Apsis\One\Model\Config\Source\System;

use Apsis\One\Model\Webhook;
use Magento\Framework\Data\OptionSourceInterface;

class WebhookTypes implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Webhook::TYPE_RECORD,
                'label' => __(Webhook::TYPE_TEXT_MAP[Webhook::TYPE_RECORD])
            ],
            [
                'value' => Webhook::TYPE_CONSENT,
                'label' => __(Webhook::TYPE_TEXT_MAP[Webhook::TYPE_CONSENT])
            ]
        ];
    }
}
