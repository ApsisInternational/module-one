<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Service\BaseService;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Api\Data\StoreInterface;

interface EventsInterface
{
    /**
     * @param array $duration
     *
     * @return void
     */
    public function setFetchDuration(array $duration): void;

    /**
     * @param int $storeId
     * @param array $ids
     *
     * @return AbstractCollection
     */
    public function getCollection(int $storeId, array $ids): AbstractCollection;

    /**
     * @param BaseService $service
     * @param array $collection
     * @param array $profiles
     * @param int $storeId
     *
     * @return array
     */
    public function getEventsArr(BaseService $service, array $collection, array $profiles, int $storeId): array;

    /**
     * @param StoreInterface $store
     * @param BaseService $baseService
     * @param array $profileColArray
     *
     * @return void
     */
    public function propagate(StoreInterface $store, BaseService $baseService, array $profileColArray): void;
}
