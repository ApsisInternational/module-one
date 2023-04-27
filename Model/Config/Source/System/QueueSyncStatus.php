<?php

namespace Apsis\One\Model\Config\Source\System;

use Apsis\One\Model\Queue;

class QueueSyncStatus extends EventSyncStatus
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = parent::toOptionArray();
        $options[] = [
            'value' => Queue::STATUS_EXPIRED,
            'label' => __(Queue::STATUS_TEXT_MAP[Queue::STATUS_EXPIRED])
        ];
        return $options;
    }
}
