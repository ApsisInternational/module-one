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
        $options = [
            [
                'value' => Profile::SYNC_STATUS_PENDING,
                'label' => 'Pending'
            ],
            [
                'value' => Profile::SYNC_STATUS_BATCHED,
                'label' => 'Batched',
            ],
            [
                'value' => Profile::SYNC_STATUS_SYNCED,
                'label' => 'Synced',
            ],
            [
                'value' => Profile::SYNC_STATUS_FAILED,
                'label' => 'Failed',
            ]
        ];

        return $options;
    }
}
