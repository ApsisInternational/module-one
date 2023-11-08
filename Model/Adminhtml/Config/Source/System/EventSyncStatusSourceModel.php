<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Apsis\One\Model\EventModel;

class EventSyncStatusSourceModel extends AbstractOptionsSource
{
    /**
     * @inheritdoc
     */
    protected function getOptionTextMap(): array
    {
        return EventModel::STATUS_TEXT_MAP;
    }
}
