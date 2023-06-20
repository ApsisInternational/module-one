<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Apsis\One\Model\EventModel;
use Magento\Framework\Data\OptionSourceInterface;

class EventSyncStatusSourceModel implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $label = $this instanceof QueueSyncStatusSourceModel ?
            'Sent' : EventModel::STATUS_TEXT_MAP[EventModel::STATUS_SYNCED];
        return [
            [
                'value' => EventModel::STATUS_PENDING,
                'label' => __(EventModel::STATUS_TEXT_MAP[EventModel::STATUS_PENDING])
            ],
            [
                'value' => EventModel::STATUS_SYNCED,
                'label' => __($label)
            ],
            [
                'value' => EventModel::STATUS_FAILED,
                'label' => __(EventModel::STATUS_TEXT_MAP[EventModel::STATUS_FAILED])
            ]
        ];
    }
}
