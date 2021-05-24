<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\Profile;

class SyncStatus implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Profile::SYNC_STATUS_PENDING,
                'label' => __(Profile::STATUS_TEXT_MAP[Profile::SYNC_STATUS_PENDING])
            ],
            [
                'value' => Profile::SYNC_STATUS_BATCHED,
                'label' => __(Profile::STATUS_TEXT_MAP[Profile::SYNC_STATUS_BATCHED])
            ],
            [
                'value' => Profile::SYNC_STATUS_SYNCED,
                'label' => __(Profile::STATUS_TEXT_MAP[Profile::SYNC_STATUS_SYNCED])
            ],
            [
                'value' => Profile::SYNC_STATUS_FAILED,
                'label' => __(Profile::STATUS_TEXT_MAP[Profile::SYNC_STATUS_FAILED])
            ],
            [
                'value' => Profile::SYNC_STATUS_NA,
                'label' => __(Profile::STATUS_TEXT_MAP[Profile::SYNC_STATUS_NA])
            ]
        ];
    }
}
