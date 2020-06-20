<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class Event
{
    const DONE_FLAG = 1;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var EventResource
     */
    protected $eventResource;

    /**
     * @var EventDataInterface
     */
    protected $eventData;

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
            'updated_at' => $this->dateTime->formatDate(true)
        ];
    }

    /**
     * @param array $eventsToRegister
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     * @param string $path
     */
    protected function registerEvents(
        array $eventsToRegister,
        ApsisCoreHelper $apsisCoreHelper,
        StoreInterface $store,
        string $path
    ) {
        if (! empty($eventsToRegister)) {
            if ($inserted = $this->eventResource->insertEvents($eventsToRegister, $apsisCoreHelper)) {
                $apsisCoreHelper->saveConfigValue(
                    $path,
                    self::DONE_FLAG,
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
            }
        }
    }

    /**
     * @param ProfileCollection $profileCollection
     *
     * @return array
     */
    protected function getFormattedProfileCollection(ProfileCollection $profileCollection)
    {
        $profileCollection->addFieldToFilter('is_customer', 1)
            ->addFieldToFilter('customer_id', ['notnull' => true]);

        $formattedProfileCollectionArray = [];
        foreach ($profileCollection as $profile) {
            $formattedProfileCollectionArray[$profile->getCustomerId()] = $profile;
        }
        return $formattedProfileCollectionArray;
    }
}
