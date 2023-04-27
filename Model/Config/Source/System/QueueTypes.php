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
                'value' => Queue::RECORD_CREATED,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::RECORD_CREATED])
            ],
            [
                'value' => Queue::RECORD_UPDATED,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::RECORD_UPDATED])
            ],
            [
                'value' => Queue::RECORD_DELETED,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::RECORD_DELETED])
            ],
            [
                'value' => Queue::CONSENT_OPT_IN,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::CONSENT_OPT_IN])
            ],
            [
                'value' => Queue::CONSENT_OPT_OUT,
                'label' => __(Queue::TYPE_TEXT_MAP[Queue::CONSENT_OPT_OUT])
            ]
        ];
    }
}
