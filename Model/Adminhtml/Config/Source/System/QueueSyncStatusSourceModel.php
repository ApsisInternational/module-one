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
        return QueueModel::STATUS_TEXT_MAP;
    }
}
