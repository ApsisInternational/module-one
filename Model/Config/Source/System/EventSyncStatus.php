<?php

namespace Apsis\One\Model\Config\Source\System;

use Apsis\One\Model\Profile;
use Apsis\One\Model\Event;
use Magento\Framework\Data\OptionSourceInterface;

class EventSyncStatus extends SyncStatus implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $statusArray = parent::toOptionArray();

        foreach ($statusArray as $index => $status) {
            if (isset($status['value']) && $status['value'] === Profile::SYNC_STATUS_NA) {
                unset($statusArray[$index]);
            }

            if (isset($status['value']) && $status['value'] === Profile::SYNC_STATUS_BATCHED) {
                unset($statusArray[$index]);
            }
        }

        $statusArray[] = ['value' => Event::SYNC_STATUS_PENDING_HISTORICAL, 'label' => __('Pending Historical')];

        return $statusArray;
    }
}
