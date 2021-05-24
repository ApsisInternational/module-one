<?php

namespace Apsis\One\Model\Config\Source\System;

use Apsis\One\Model\Profile;
use Magento\Framework\Data\OptionSourceInterface;

class EventSyncStatus extends SyncStatus implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $statusArray = parent::toOptionArray();
        foreach ($statusArray as $index => $status) {
            if (isset($status['value']) && $status['value'] === Profile::SYNC_STATUS_NA) {
                unset($statusArray[$index]);
            }
        }
        return $statusArray;
    }
}
