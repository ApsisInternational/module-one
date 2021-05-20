<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ProductReviewCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\Collection as ProductReviewCollection;
use Magento\Review\Model\Review;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product;
use Apsis\One\Model\Events\Historical\Reviews\Data as ReviewEventData;
use Apsis\One\Model\Event;
use Magento\Review\Model\ReviewFactory;

class Reviews extends HistoricalEvent implements EventHistoryInterface
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
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollection $profileCollection
     * @param array $duration
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $duration
    ) {
        if ((boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_REVIEW
        )) {
            try {
                if (! empty($profileCollectionArray =
                        $this->getFormattedProfileCollection($profileCollection, $apsisCoreHelper)) &&
                    ! empty($reviewCollection = $this->getReviewCollection(
                        $apsisCoreHelper,
                        $store,
                        array_keys($profileCollectionArray),
                        $duration
                    )) &&
                    ! empty($productCollectionArray = $this->getProductCollectionArray(
                        $store,
                        $apsisCoreHelper,
                        $reviewCollection->getColumnValues('entity_pk_value')
                    ))
                ) {
                    $eventsToRegister = $this->getEventsToRegister(
                        $apsisCoreHelper,
                        $reviewCollection,
                        $profileCollectionArray,
                        $productCollectionArray
                    );
                    $this->registerEvents(
                        $eventsToRegister,
                        $apsisCoreHelper,
                        $store,
                        ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_REVIEW_HISTORY_DONE_FLAG
                    );
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
            }
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProductReviewCollection $reviewCollection
     * @param array $profileCollectionArray
     * @param array $productCollectionArray
     *
     * @return array
     */
    private function getEventsToRegister(
        ApsisCoreHelper $apsisCoreHelper,
        ProductReviewCollection $reviewCollection,
        array $profileCollectionArray,
        array $productCollectionArray
    ) {
        $eventsToRegister = [];
        /** @var Review $review */
        foreach ($reviewCollection as $review) {
            try {
                if (isset($profileCollectionArray[$review->getCustomerId()]) &&
                    isset($productCollectionArray[$review->getEntityPkValue()])
                ) {
                    $review = $this->reviewFactory->create()->load($review->getId());
                    $eventData = $this->eventData->getDataArr(
                        $review,
                        $productCollectionArray[$review->getEntityPkValue()],
                        $apsisCoreHelper
                    );
                    if (! empty($eventData)) {
                        $eventDataForEvent = $this->getEventData(
                            $review->getStoreId(),
                            $profileCollectionArray[$review->getCustomerId()],
                            Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
                            $review->getCreatedAt(),
                            $apsisCoreHelper->serialize($eventData),
                            $apsisCoreHelper
                        );
                        if (! empty($eventDataForEvent)) {
                            $eventsToRegister[] = $eventDataForEvent;
                        }
                    }
                }
            } catch (Exception $e) {
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
            /** @var Product $product */
            foreach ($productCollection as $product) {
                $productCollectionArray[$product->getId()] = $product;
            }
        } catch (Exception $e) {
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
     * @return array|ProductReviewCollection
     */
    private function getReviewCollection(
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
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
