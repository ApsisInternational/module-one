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
                'value' => 'NA',
                'label' => __('N/A')
            ],
            [
                'value' => Profile::SYNC_STATUS_PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => Profile::SYNC_STATUS_BATCHED,
                'label' => __('Batched')
            ],
            [
                'value' => Profile::SYNC_STATUS_SYNCED,
                'label' => __('Synced')
            ],
            [
                'value' => Profile::SYNC_STATUS_FAILED,
                'label' => __('Failed')
            ]
        ];
    }
}
