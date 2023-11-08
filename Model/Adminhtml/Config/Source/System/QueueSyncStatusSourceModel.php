<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Apsis\One\Model\EventModel;
use Apsis\One\Model\QueueModel;

class QueueSyncStatusSourceModel extends AbstractOptionsSource
{
    /**
     * @inheritdoc
     */
    protected function getOptionTextMap(): array
    {
        $eventTextMap = EventModel::STATUS_TEXT_MAP;
        $rms = [EventModel::STATUS_SYNCED, EventModel::STATUS_HISTORICAL];
        foreach ($rms as $rm) {
            if (isset($eventTextMap[$rm])) {
                unset($eventTextMap[$rm]);
            }
        }
        return array_merge($eventTextMap, QueueModel::STATUS_TEXT_MAP);
    }
}
