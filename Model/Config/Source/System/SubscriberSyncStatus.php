<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\Profile;

class SubscriberSyncStatus extends SyncStatus implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $statusArray = parent::toOptionArray();
        $statusArray[] = [
            'value' => Profile::SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE,
            'label' => __(Profile::STATUS_TEXT_MAP[Profile::SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE])
        ];
        return $statusArray;
    }
}
