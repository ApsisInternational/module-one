<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\Data\Review\ReviewData;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Stdlib\DateTime;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ProductReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;
use Magento\Review\Model\ReviewFactory;

class ReviewEvents extends AbstractEvents
{
    /**
     * @var ReviewFactory
     */
    private ReviewFactory $reviewFactory;

    /**
     * @var ReviewResource
     */
    private ReviewResource $reviewResource;

    private ProductReviewCollectionFactory $productReviewCollectionFactory;

    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param ReviewData $entityData
     * @param ProductReviewCollectionFactory $productReviewCollectionFactory
     * @param ReviewFactory $reviewFactory
     * @param ReviewResource $reviewResource
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        ReviewData $entityData,
        ProductReviewCollectionFactory $productReviewCollectionFactory,
        ReviewFactory $reviewFactory,
        ReviewResource $reviewResource
    ) {
        $this->productReviewCollectionFactory = $productReviewCollectionFactory;
        $this->reviewResource = $reviewResource;
        $this->reviewFactory = $reviewFactory;
        parent::__construct($dateTime, $eventResource, $entityData);
    }

    /**
     * @inheirtDoc
     */
    public function getCollection(int $storeId, array $ids): AbstractCollection
    {
        return $this->productReviewCollectionFactory
            ->create()
            ->addStoreFilter([$storeId])
            ->addFieldToFilter('customer_id', ['in' => $ids])
            ->addFieldToFilter('main_table.entity_id', 1)
            ->addFieldToFilter('main_table.created_at', $this->fetchDuration);
    }

    /**
     * @inheirtDoc
     */
    public function getEventsArr(BaseService $service, array $collection, array $profiles, int $storeId): array
    {
        $events = [];
        /** @var Review $entity */
        foreach ($collection as $entity) {
            $review = $this->getReviewModel();
            $this->reviewResource->load($review, $entity->getId());
            $review->setData('store_id', $storeId);
            $profile = $profiles[$review->getCustomerId()];

            $data = ['main' => $this->entityData->getDataArr($review, $service), 'sub' => ''];
            $events[] = $this->getDataForInsertion($profile, EventModel::REVIEW, $review->getCreatedAt(), $data);
        }
        return $events;
    }

    /**
     * @return Review
     */
    private function getReviewModel(): Review
    {
        return $this->reviewFactory->create();
    }
}
