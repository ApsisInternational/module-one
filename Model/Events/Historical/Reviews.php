<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ProductReviewCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\Collection as ProductReviewCollection;
use Magento\Review\Model\Review;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product;
use Apsis\One\Model\Events\Historical\Reviews\Data as ReviewEventData;
use Apsis\One\Model\Event;

class Reviews extends HistoricalEvent implements EventHistoryInterface
{
    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var ProductReviewCollectionFactory
     */
    private $reviewProductCollectionFactory;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ReviewEventData
     */
    private $reviewEventData;

    /**
     * Reviews constructor.
     *
     * @param ProductReviewCollectionFactory $reviewProductCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param EventResource $eventResource
     * @param ReviewEventData $reviewEventData
     */
    public function __construct(
        ProductReviewCollectionFactory $reviewProductCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        EventResource $eventResource,
        ReviewEventData $reviewEventData
    ) {
        $this->reviewEventData = $reviewEventData;
        $this->eventResource = $eventResource;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->reviewProductCollectionFactory = $reviewProductCollectionFactory;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $profileCollectionArray
     * @param array $duration
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        array $profileCollectionArray,
        array $duration
    ) {
        if ((boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_REVIEW
        )) {
            try {
                if (! empty($reviewCollection = $this->getReviewCollection(
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
                    if (! empty($eventsToRegister)) {
                        $this->eventResource->insertEvents($eventsToRegister, $apsisCoreHelper);
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
                    $eventsToRegister[] = $this->getEventData(
                        $profileCollectionArray[$review->getCustomerId()],
                        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
                        $review->getCreatedAt(),
                        $apsisCoreHelper->serialize(
                            $this->reviewEventData->getDataArr(
                                $review,
                                $productCollectionArray[$review->getEntityPkValue()],
                                $apsisCoreHelper
                            )
                        )
                    );
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return [];
        }
    }
}
