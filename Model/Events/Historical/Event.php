<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Event as EventModel;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Throwable;

abstract class Event implements EventHistoryInterface
{
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
     * @param int $storeId
     * @param Profile $profile
     * @param int $eventType
     * @param string $createdAt
     * @param string $eventData
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param string $eventSubData
     *
     * @return array
     */
    protected function getEventData(
        int $storeId,
        Profile $profile,
        int $eventType,
        string $createdAt,
        string $eventData,
        ApsisCoreHelper $apsisCoreHelper,
        string $eventSubData = ''
    ) {
        try {
            return [
                'event_type' => $eventType,
                'event_data' => $eventData,
                'sub_event_data' => $eventSubData,
                'profile_id' => $profile->getId(),
                'customer_id' => $profile->getCustomerId(),
                'subscriber_id' => $profile->getSubscriberId(),
                'store_id' => $storeId,
                'email' => $profile->getEmail(),
                'status' => EventModel::SYNC_STATUS_PENDING_HISTORICAL,
                'created_at' => $createdAt,
                'updated_at' => $this->dateTime->formatDate(true)
            ];
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param array $eventsToRegister
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return int
     */
    protected function registerEvents(array $eventsToRegister, ApsisCoreHelper $apsisCoreHelper)
    {
        if (empty($eventsToRegister)) {
            return 0;
        }

        return $this->eventResource->insertEvents($eventsToRegister, $apsisCoreHelper);
    }

    /**
     * @param array $filter
     * @param array $duration
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    protected function getCollectionArray(
        array $filter,
        array $duration,
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        $collectionArray = [];

        try {
            foreach (array_chunk($filter, self::QUERY_LIMIT) as $filterChunk) {
                $collection = $this->createCollection($apsisCoreHelper, $store, $filterChunk, $duration);
                if ($collection->getSize()) {
                    foreach ($collection as $item) {
                        try {
                            $collectionArray[$item->getId()] =  $item;
                        } catch (Throwable $e) {
                            $apsisCoreHelper->logError(__METHOD__, $e);
                            continue;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $collectionArray;
    }
}
