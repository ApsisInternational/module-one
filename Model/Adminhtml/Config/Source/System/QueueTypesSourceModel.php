<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Apsis\One\Model\QueueModel;
use Magento\Framework\Data\OptionSourceInterface;

class QueueTypesSourceModel implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => QueueModel::RECORD_CREATED,
                'label' => __(QueueModel::TYPE_TEXT_MAP[QueueModel::RECORD_CREATED])
            ],
            [
                'value' => QueueModel::RECORD_UPDATED,
                'label' => __(QueueModel::TYPE_TEXT_MAP[QueueModel::RECORD_UPDATED])
            ],
            [
                'value' => QueueModel::RECORD_DELETED,
                'label' => __(QueueModel::TYPE_TEXT_MAP[QueueModel::RECORD_DELETED])
            ],
            [
                'value' => QueueModel::CONSENT_OPT_IN,
                'label' => __(QueueModel::TYPE_TEXT_MAP[QueueModel::CONSENT_OPT_IN])
            ],
            [
                'value' => QueueModel::CONSENT_OPT_OUT,
                'label' => __(QueueModel::TYPE_TEXT_MAP[QueueModel::CONSENT_OPT_OUT])
            ]
        ];
    }
}
