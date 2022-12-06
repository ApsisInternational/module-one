<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\ProfileBatch;

class BatchSyncStatus implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => ProfileBatch::SYNC_STATUS_PENDING,
                'label' => __(ProfileBatch::STATUS_TEXT_MAP[ProfileBatch::SYNC_STATUS_PENDING])
            ],
            [
                'value' => ProfileBatch::SYNC_STATUS_PROCESSING,
                'label' => __(ProfileBatch::STATUS_TEXT_MAP[ProfileBatch::SYNC_STATUS_PROCESSING])
            ],
            [
                'value' => ProfileBatch::SYNC_STATUS_COMPLETED,
                'label' => __(ProfileBatch::STATUS_TEXT_MAP[ProfileBatch::SYNC_STATUS_COMPLETED])
            ],
            [
                'value' => ProfileBatch::SYNC_STATUS_FAILED,
                'label' => __(ProfileBatch::STATUS_TEXT_MAP[ProfileBatch::SYNC_STATUS_FAILED])
            ],
            [
                'value' => ProfileBatch::SYNC_STATUS_ERROR,
                'label' => __(ProfileBatch::STATUS_TEXT_MAP[ProfileBatch::SYNC_STATUS_ERROR])
            ]
        ];
    }
}
