<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\Data\Review\ReviewData;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Stdlib\DateTime;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ProductReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Api\Data\StoreInterface;
use Throwable;

class ReviewEvents extends AbstractEvents
{
    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var ReviewFactory
     */
    private ReviewFactory $reviewFactory;

    /**
     * @var ReviewResource
     */
    private ReviewResource $reviewResource;

    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param ProductReviewCollectionFactory $collectionFactory
     * @param ReviewData $eventData
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ReviewFactory $reviewFactory
     * @param ReviewResource $reviewResource
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        ProductReviewCollectionFactory $collectionFactory,
        ReviewData $eventData,
        ProductCollectionFactory $productCollectionFactory,
        ReviewFactory $reviewFactory,
        ReviewResource $reviewResource
    ) {
        $this->reviewResource = $reviewResource;
        $this->reviewFactory = $reviewFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($dateTime, $eventResource, $collectionFactory, $eventData);
    }

    /**
     * @inheirtDoc
     */
    public function process(StoreInterface $store, BaseService $baseService, array $profileColArray): void
    {
        $eventsToRegister = $this->findAndRegister($store, $baseService, $profileColArray);
        $this->registerEvents($eventsToRegister, $baseService, $store->getId(), 'Product Reviewed');
    }

    /**
     * @return Review
     */
    private function getReviewModel(): Review
    {
        return $this->reviewFactory->create();
    }

    /**
     * @inheirtDoc
     */
    protected function getEventsToRegister(
        BaseService $baseService,
        array $entityCollectionArr,
        array $profileCollectionArray,
        StoreInterface $store
    ): array {
        $eventsToRegister = [];
        $productCollectionArray = $this->getProductCollectionArray(
            $store,
            $baseService,
            $this->getProductIdsFromCollection($entityCollectionArr, $baseService)
        );
        if (empty($productCollectionArray)) {
            return $eventsToRegister;
        }

        /** @var Review $entity */
        foreach ($entityCollectionArr as $entity) {
            try {
                $review = $this->getReviewModel();
                $this->reviewResource->load($review, $entity->getId());
                if (! $review->getId() || ! isset($profileCollectionArray[$review->getCustomerId()]) ||
                    empty($profile = $profileCollectionArray[$review->getCustomerId()]) ||
                    ! isset($productCollectionArray[$review->getEntityPkValue()]) ||
                    empty($product = $productCollectionArray[$review->getEntityPkValue()])
                ) {
                    continue;
                }

                $eventData = $this->eventData->getReviewData(
                    $profile,
                    $review,
                    $product,
                    $baseService
                );

                if (! empty($eventData)) {
                    $eventDataForEvent = $this->getFormattedEventDataForRecord(
                        $review->getStoreId(),
                        $profile,
                        EventModel::EVENT_PRODUCT_REVIEWED,
                        $review->getCreatedAt(),
                        json_encode($eventData),
                        $baseService
                    );
                    if (! empty($eventDataForEvent)) {
                        $eventsToRegister[] = $eventDataForEvent;
                    }
                }
            } catch (Throwable $e) {
                $baseService->logError(__METHOD__, $e);
                continue;
            }
        }

        return $eventsToRegister;
    }

    /**
     * @return Collection
     */
    private function getProductCollection(): Collection
    {
        return $this->productCollectionFactory->create();
    }

    /**
     * @param StoreInterface $store
     * @param BaseService $baseService
     * @param array $productIds
     *
     * @return array
     */
    private function getProductCollectionArray(
        StoreInterface  $store,
        BaseService $baseService,
        array $productIds
    ): array {
        $productCollectionArray = [];

        try {
            $productCollection = $this->getProductCollection()
                ->addAttributeToSelect('*')
                ->addStoreFilter($store->getId())
                ->addIdFilter($productIds)
                ->addUrlRewrite()
                ->addPriceData();

            foreach ($productCollection as $product) {
                $productCollectionArray[$product->getId()] = $product;
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $productCollectionArray;
    }

    /**
     * @param array $collection
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getProductIdsFromCollection(array $collection, BaseService $baseService): array
    {
        $productIds = [];

        try {
            /** @var Review $item */
            foreach ($collection as $item) {
                try {
                    if (! in_array($item->getEntityPkValue(), $productIds)) {
                        $productIds[] = $item->getEntityPkValue();
                    }
                } catch (Throwable $e) {
                    $baseService->logError(__METHOD__, $e);
                    continue;
                }
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $productIds;
    }
}
