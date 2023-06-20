<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Apsis\One\Model\WebhookModel;
use Magento\Framework\Data\OptionSourceInterface;

class WebhookTypesSourceModel implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => WebhookModel::TYPE_RECORD,
                'label' => __(WebhookModel::TYPE_TEXT_MAP[WebhookModel::TYPE_RECORD])
            ],
            [
                'value' => WebhookModel::TYPE_CONSENT,
                'label' => __(WebhookModel::TYPE_TEXT_MAP[WebhookModel::TYPE_CONSENT])
            ]
        ];
    }
}
