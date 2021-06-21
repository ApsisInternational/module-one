<?php

namespace Apsis\One\Model\Events\Historical\Reviews;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Framework\Model\AbstractModel;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\CollectionFactory as VoteCollectionFactory;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Throwable;

class Data extends EventData implements EventDataInterface
{
    /**
     * @var VoteCollectionFactory
     */
    private $voteCollectionFactory;

    /**
     * @var MagentoProduct
     */
    private $product;

    /**
     * Data constructor.
     *
     * @param ProductServiceProvider $productServiceProvider
     * @param VoteCollectionFactory $voteCollectionFactory
     */
    public function __construct(
        ProductServiceProvider $productServiceProvider,
        VoteCollectionFactory $voteCollectionFactory
    ) {
        $this->voteCollectionFactory = $voteCollectionFactory;
        parent::__construct($productServiceProvider);
    }

    /**
     * @param Review $reviewObject
     * @param MagentoProduct $product
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getDataArr(Review $reviewObject, MagentoProduct $product, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            if (! $product->getId()) {
                return [];
            }

            $this->product = $product;
            return $this->getProcessedDataArr($reviewObject, $apsisCoreHelper);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getProcessedDataArr(AbstractModel $model, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $voteCollection = $this->voteCollectionFactory->create()->setReviewFilter($model->getReviewId());
            return [
                'reviewId' => (int) $model->getReviewId(),
                'customerId' => (int) $model->getCustomerId(),
                'websiteName' => (string) $apsisCoreHelper->getWebsiteNameFromStoreId($model->getStoreId()),
                'storeName' => (string) $apsisCoreHelper->getStoreNameFromId($model->getStoreId()),
                'nickname' => (string) $model->getNickname(),
                'reviewTitle' => (string) $model->getTitle(),
                'reviewDetail' => (string) $model->getDetail(),
                'productId' => (int) $this->product->getId(),
                'sku' => (string) $this->product->getSku(),
                'name' => (string) $this->product->getName(),
                'productUrl' => (string) $this->product->getProductUrl(),
                'productReviewUrl' => (string) $model->getReviewUrl(),
                'productImageUrl' => (string) $this->productServiceProvider->getProductImageUrl($this->product),
                'catalogPriceAmount' => $apsisCoreHelper->round($this->product->getPrice()),
                'ratingStarValue' => ($voteCollection->getSize()) ?
                    (int) $voteCollection->getFirstItem()->getValue() : 0
            ];
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
