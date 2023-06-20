<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Apsis\One\Model\QueueModel;

class QueueSyncStatusSourceModel extends EventSyncStatusSourceModel
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = parent::toOptionArray();
        $options[] = [
            'value' => QueueModel::STATUS_EXPIRED,
            'label' => __(QueueModel::STATUS_TEXT_MAP[QueueModel::STATUS_EXPIRED])
        ];
        return $options;
    }
}
