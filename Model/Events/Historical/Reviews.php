<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Throwable;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ProductReviewCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\Collection as ProductReviewCollection;
use Magento\Review\Model\Review;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Apsis\One\Model\Events\Historical\Reviews\Data as ReviewEventData;
use Apsis\One\Model\Event;
use Magento\Review\Model\ReviewFactory;

class Reviews extends HistoricalEvent
{
    /**
     * @var ProductReviewCollectionFactory
     */
    private $reviewProductCollectionFactory;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ReviewFactory
     */
    private $reviewFactory;

    /**
     * Reviews constructor.
     *
     * @param DateTime $dateTime
     * @param ProductReviewCollectionFactory $reviewProductCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param EventResource $eventResource
     * @param ReviewEventData $reviewEventData
     * @param ReviewFactory $reviewFactory
     */
    public function __construct(
        DateTime $dateTime,
        ProductReviewCollectionFactory $reviewProductCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        EventResource $eventResource,
        ReviewEventData $reviewEventData,
        ReviewFactory $reviewFactory
    ) {
        $this->reviewFactory = $reviewFactory;
        $this->dateTime = $dateTime;
        $this->eventData = $reviewEventData;
        $this->eventResource = $eventResource;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->reviewProductCollectionFactory = $reviewProductCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $duration,
        array $profileCollectionArray
    ) {
        try {
            if (empty($profileCollectionArray)) {
                return;
            }

            $reviewCollection = $this->getCollectionArray(
                array_keys($profileCollectionArray),
                $duration,
                $store,
                $apsisCoreHelper
            );
            if (empty($reviewCollection)) {
                return;
            }

            $productCollectionArray = $this->getProductCollectionArray(
                $store,
                $apsisCoreHelper,
                $this->getProductIdsFromCollection($reviewCollection, $apsisCoreHelper)
            );
            if (empty($productCollectionArray)) {
                return;
            }

            $eventsToRegister = $this->getEventsToRegister(
                $apsisCoreHelper,
                $reviewCollection,
                $profileCollectionArray,
                $productCollectionArray
            );

            $status = $this->registerEvents($eventsToRegister, $apsisCoreHelper);

            if ($status) {
                $info = [
                    'Total Events Inserted' => $status,
                    'Store Id' => $store->getId()
                ];
                $apsisCoreHelper->debug(__METHOD__, $info);
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $reviewCollection
     * @param array $profileCollectionArray
     * @param array $productCollectionArray
     *
     * @return array
     */
    private function getEventsToRegister(
        ApsisCoreHelper $apsisCoreHelper,
        array $reviewCollection,
        array $profileCollectionArray,
        array $productCollectionArray
    ) {
        $eventsToRegister = [];

        /** @var Review $review */
        foreach ($reviewCollection as $review) {
            try {
                if (empty($review = $this->reviewFactory->create()->load($review->getId())) ||
                    empty($profile = $profileCollectionArray[$review->getCustomerId()]) ||
                    empty($product = $productCollectionArray[$review->getEntityPkValue()])
                ) {
                    continue;
                }

                $eventData = $this->eventData->getDataArr(
                    $review,
                    $product,
                    $apsisCoreHelper
                );

                if (! empty($eventData)) {
                    $eventDataForEvent = $this->getEventData(
                        $review->getStoreId(),
                        $profile,
                        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
                        $review->getCreatedAt(),
                        $apsisCoreHelper->serialize($eventData),
                        $apsisCoreHelper
                    );
                    if (! empty($eventDataForEvent)) {
                        $eventsToRegister[] = $eventDataForEvent;
                    }
                }
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                continue;
            }
        }

        return $eventsToRegister;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $productIds
     *
     * @return array
     */
    private function getProductCollectionArray(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        array $productIds
    ) {
        $productCollectionArray = [];

        try {
            $productCollection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addStoreFilter($store->getId())
                ->addIdFilter($productIds)
                ->addUrlRewrite()
                ->addPriceData();

            foreach ($productCollection as $product) {
                $productCollectionArray[$product->getId()] = $product;
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $productCollectionArray;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     * @param array $customerIds
     * @param array $duration
     *
     * @return ProductReviewCollection|array
     */
    protected function createCollection(
        ApsisCoreHelper $apsisCoreHelper,
        StoreInterface $store,
        array $customerIds,
        array $duration
    ) {
        try {
            return $this->reviewProductCollectionFactory->create()
                ->addStoreFilter($store->getId())
                ->addStatusFilter(Review::STATUS_APPROVED)
                ->addFieldToFilter('main_table.entity_id', 1)
                ->addFieldToFilter('main_table.created_at', $duration)
                ->addFieldToFilter('customer_id', ['in' => $customerIds]);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param array $collection
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    private function getProductIdsFromCollection(array $collection, ApsisCoreHelper $apsisCoreHelper)
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
                    $apsisCoreHelper->logError(__METHOD__, $e);
                    continue;
                }
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $productIds;
    }
}
