<?php

namespace Apsis\One\Model\Config\Source\System;

use Apsis\One\Model\Event;
use Magento\Framework\Data\OptionSourceInterface;

class EventSyncStatus implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Event::STATUS_PENDING,
                'label' => __(Event::STATUS_TEXT_MAP[Event::STATUS_PENDING])
            ],
            [
                'value' => Event::STATUS_SYNCED,
                'label' => __(Event::STATUS_TEXT_MAP[Event::STATUS_SYNCED])
            ],
            [
                'value' => Event::STATUS_FAILED,
                'label' => __(Event::STATUS_TEXT_MAP[Event::STATUS_FAILED])
            ]
        ];
    }
}
