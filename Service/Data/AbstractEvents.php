<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;

abstract class AbstractEvents implements EventsInterface
{
    const QUERY_LIMIT = 5000;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @var EventResource
     */
    protected EventResource $eventResource;

    /**
     * @var EntityDataInterface
     */
    protected EntityDataInterface $entityData;

    /**
     * @var array
     */
    protected array $fetchDuration = [];

    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param EntityDataInterface $entityData
     */
    public function __construct(DateTime $dateTime, EventResource $eventResource, EntityDataInterface $entityData)
    {
        $this->dateTime = $dateTime;
        $this->eventResource = $eventResource;
        $this->entityData = $entityData;
    }

    /**
     * @inheirtDoc
     */
    public function setFetchDuration(array $duration): void
    {
        $this->fetchDuration = $duration;
    }

    /**
     * @inheirtDoc
     */
    public function propagate(StoreInterface $store, BaseService $baseService, array $profileColArray): void
    {
        $eventsToInsert = $this->findEvents($store, $baseService, $profileColArray);
        $this->eventResource->insertMultipleItems($eventsToInsert, $baseService);
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $baseService
     * @param array $profileColArray
     *
     * @return array
     */
    protected function findEvents(StoreInterface $store, BaseService $baseService, array $profileColArray): array
    {
        $entityCollection = $this->getArrayFromEntityCollection(array_keys($profileColArray), $store->getId());
        return $this->getEventsArr($baseService, $entityCollection, $profileColArray, $store->getId());
    }

    /**
     * @param array $filter
     * @param int $storeId
     *
     * @return array
     */
    protected function getArrayFromEntityCollection(array $filter, int $storeId): array
    {
        $collectionArray = [];
        foreach (array_chunk($filter, self::QUERY_LIMIT) as $filterChunk) {
            $collection = $this->getCollection($storeId, $filterChunk);
            foreach ($collection as $item) {
                $collectionArray[$item->getId()] =  $item;
            }
        }
        return $collectionArray;
    }

    /**
     * @param ProfileModel $profile
     * @param int $type
     * @param string $createdAt
     * @param $eventData
     *
     * @return array
     */
    protected function getDataForInsertion(ProfileModel $profile, int $type, string $createdAt, $eventData): array
    {
        return [
            'type' => $type,
            'event_data' => json_encode($eventData['main']),
            'sub_event_data' => json_encode($eventData['sub']),
            'profile_id' => $profile->getId(),
            'customer_id' => $profile->getCustomerId(),
            'subscriber_id' => $profile->getSubscriberId(),
            'store_id' => $profile->getStoreId(),
            'email' => $profile->getEmail(),
            'sync_status' => EventModel::STATUS_HISTORICAL,
            'created_at' => $createdAt,
            'updated_at' => $this->dateTime->formatDate(true)
        ];
    }
}
