<?php

namespace Apsis\One\Model\Config\Source\System;

use Apsis\One\Model\Queue;
use Magento\Framework\Data\OptionSourceInterface;

class QueueTypes implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Queue::TYPE_RECORD_CREATED,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::TYPE_RECORD_CREATED])
            ],
            [
                'value' => Queue::TYPE_RECORD_UPDATED,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::TYPE_RECORD_UPDATED])
            ],
            [
                'value' => Queue::TYPE_RECORD_DELETED,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::TYPE_RECORD_DELETED])
            ],
            [
                'value' => Queue::TYPE_CONSENT_OPT_IN,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::TYPE_CONSENT_OPT_IN])
            ],
            [
                'value' => Queue::TYPE_CONSENT_OPT_OUT,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::TYPE_CONSENT_OPT_OUT])
            ]
        ];
    }
}
