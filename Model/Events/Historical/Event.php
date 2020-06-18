<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Profile;

class Event
{
    /**
     * @param Profile $profile
     * @param int $eventType
     * @param string $createdAt
     * @param string $eventData
     * @param string $eventSubData
     *
     * @return array
     */
    protected function getEventData(
        Profile $profile,
        int $eventType,
        string $createdAt,
        string $eventData,
        string $eventSubData = ''
    ) {
        return [
            'event_type' => $eventType,
            'event_data' => $eventData,
            'sub_event_data' => $eventSubData,
            'profile_id' => (int) $profile->getId(),
            'customer_id' => (int) $profile->getCustomerId(),
            'subscriber_id' => (int) $profile->getSubscriberId(),
            'store_id' => (int) $profile->getStoreId(),
            'email' => (string) $profile->getEmail(),
            'status' => Profile::SYNC_STATUS_PENDING,
            'created_at' => $createdAt,
            'updated_at' => $createdAt
        ];
    }
}
