<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteColFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderColFactory;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ProductReviewColFactory;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistItemColFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Review\Model\ResourceModel\Review\Collection as ProductReviewCollection;
use Magento\Wishlist\Model\ResourceModel\Item\Collection as WishlistItemCollection;
use Throwable;

abstract class AbstractEvents
{
    const QUERY_LIMIT = 500;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @var EventResource
     */
    protected EventResource $eventResource;

    /**
     * @var QuoteColFactory|OrderColFactory|ProductReviewColFactory|WishlistItemColFactory
     */
    protected QuoteColFactory|OrderColFactory|ProductReviewColFactory|WishlistItemColFactory $collectionFactory;

    /**
     * @var AbstractData
     */
    protected AbstractData $eventData;

    /**
     * @var array
     */
    protected array $fetchDuration = [];

    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param QuoteColFactory|OrderColFactory|ProductReviewColFactory|WishlistItemColFactory $collectionFactory
     * @param AbstractData $eventData
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        QuoteColFactory|OrderColFactory|ProductReviewColFactory|WishlistItemColFactory $collectionFactory,
        AbstractData $eventData
    ) {
        $this->dateTime = $dateTime;
        $this->eventResource = $eventResource;
        $this->collectionFactory = $collectionFactory;
        $this->eventData = $eventData;
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $baseService
     * @param array $profileColArray
     *
     * @return void
     */
    abstract public function process(
        StoreInterface $store,
        BaseService $baseService,
        array $profileColArray
    ): void;

    /**
     * @param BaseService $baseService
     * @param array $entityCollectionArr
     * @param array $profileCollectionArray
     * @param StoreInterface $store
     *
     * @return array
     */
    abstract protected function getEventsToRegister(
        BaseService $baseService,
        array $entityCollectionArr,
        array $profileCollectionArray,
        StoreInterface $store
    ): array;

    /**
     * @param array $duration
     *
     * @return AbstractEvents
     */
    public function setFetchDuration(array $duration): AbstractEvents
    {
        $this->fetchDuration = $duration;
        return $this;
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $baseService
     * @param array $profileColArray
     *
     * @return array
     */
    protected function findAndRegister(
        StoreInterface  $store,
        BaseService $baseService,
        array $profileColArray
    ): array {
        try {
            if (empty($profileColArray)) {
                return [];
            }

            $collection = $this->getFormattedArrFromCollection(
                array_keys($profileColArray),
                $store,
                $baseService
            );
            if (empty($collection)) {
                return [];
            }

            return static::getEventsToRegister(
                $baseService,
                $collection,
                $profileColArray,
                $store
            );
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param int $storeId
     * @param ProfileModel $profile
     * @param int $eventType
     * @param string $createdAt
     * @param string $eventData
     * @param BaseService $baseService
     * @param string $eventSubData
     *
     * @return array
     */
    protected function getFormattedEventDataForRecord(
        int $storeId,
        ProfileModel $profile,
        int $eventType,
        string $createdAt,
        string $eventData,
        BaseService $baseService,
        string $eventSubData = ''
    ): array {
        try {
            return [
                'type' => $eventType,
                'event_data' => $eventData,
                'sub_event_data' => $eventSubData,
                'profile_id' => $profile->getId(),
                'customer_id' => $profile->getCustomerId(),
                'subscriber_id' => $profile->getSubscriberId(),
                'store_id' => $storeId,
                'email' => $profile->getEmail(),
                'sync_status' => EventModel::STATUS_PENDING,
                'created_at' => $createdAt,
                'updated_at' => $this->dateTime->formatDate(true)
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param array $items
     * @param BaseService $baseService
     * @param int $storeId
     * @param string $type
     *
     * @return void
     */
    protected function registerEvents(array $items, BaseService $baseService, int $storeId, string $type): void
    {
        if (empty($items)) {
            return;
        }

        $status = $this->eventResource->insertMultipleItems($items, $baseService);
        if ($status && getenv('APSIS_DEVELOPER')) {
            $info = [
                'Total Events Inserted' => $status,
                'Store Id' => $storeId,
                'Type' => $type,
                'Fetch From' => $this->fetchDuration['from'] ?? null,
                'Fetch To' => $this->fetchDuration['to'] ?? null
            ];
            $baseService->debug(__METHOD__, $info);
        }
    }

    /**
     * @param array $filter
     * @param StoreInterface $store
     * @param BaseService $baseService
     *
     * @return array
     */
    protected function getFormattedArrFromCollection(
        array $filter,
        StoreInterface $store,
        BaseService $baseService
    ): array {
        $collectionArray = [];

        try {
            foreach (array_chunk($filter, self::QUERY_LIMIT) as $filterChunk) {
                $collection = $this->getCollection($baseService, $store->getId(), $filterChunk);
                if ($collection && $collection->getSize()) {
                    foreach ($collection as $item) {
                        try {
                            $collectionArray[$item->getId()] =  $item;
                        } catch (Throwable $e) {
                            $baseService->logError(__METHOD__, $e);
                            continue;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $collectionArray;
    }

    /**
     * @param BaseService $baseService
     * @param int $storeId
     * @param array $values
     *
     * @return AbstractCollection|null
     */
    private function getCollection(BaseService $baseService, int $storeId, array $values): AbstractCollection|null
    {
        try {
            if (empty($this->fetchDuration)) {
                return null;
            }

            $collection =  $this->collectionFactory->create();
            if ($collection instanceof QuoteCollection || $collection instanceof OrderCollection) {
                $collection->addFieldToFilter('main_table.store_id', $storeId)
                    ->addFieldToFilter('main_table.updated_at', $this->fetchDuration);
            }

            if ($collection instanceof ProductReviewCollection || $collection instanceof WishlistItemCollection) {
                $collection->addStoreFilter([$storeId]);
            }

            if ($collection instanceof QuoteCollection) {
                $collection->addFieldToFilter('main_table.customer_id', ['in' => $values]);
            }

            if ($collection instanceof OrderCollection) {
                $collection->addFieldToFilter('main_table.customer_email', ['in' => $values]);
            }

            if ($collection instanceof ProductReviewCollection) {
                $collection->addFieldToFilter('customer_id', ['in' => $values])
                    ->addFieldToFilter('main_table.entity_id', 1)
                    ->addFieldToFilter('main_table.created_at', $this->fetchDuration);
            }

            if ($collection instanceof WishlistItemCollection) {
                $collection->addFieldToFilter('wishlist_id', ['in' => $values])
                    ->addFieldToFilter('main_table.added_at', $this->fetchDuration)
                    ->setVisibilityFilter()
                    ->setSalableFilter();
                $collection->getSelect()->group('wishlist_item_id');
            }

            return $collection;
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return null;
        }
    }
}
