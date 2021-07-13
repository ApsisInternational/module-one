<?php

namespace Apsis\One\Model\Events\Historical\Reviews;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
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
     * Data constructor.
     *
     * @param ProductServiceProvider $productServiceProvider
     * @param ProductRepositoryInterface $productRepository
     * @param VoteCollectionFactory $voteCollectionFactory
     */
    public function __construct(
        ProductServiceProvider $productServiceProvider,
        ProductRepositoryInterface $productRepository,
        VoteCollectionFactory $voteCollectionFactory
    ) {
        $this->voteCollectionFactory = $voteCollectionFactory;
        parent::__construct($productServiceProvider, $productRepository);
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
            $this->apsisCoreHelper = $apsisCoreHelper;
            $this->fetchProduct($product);
            return $this->getProcessedDataArr($reviewObject);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function getProcessedDataArr(AbstractModel $model)
    {
        try {
            $voteCollection = $this->voteCollectionFactory->create()->setReviewFilter($model->getReviewId());
            return [
                'reviewId' => (int) $model->getReviewId(),
                'customerId' => (int) $model->getCustomerId(),
                'websiteName' => (string) $this->apsisCoreHelper->getWebsiteNameFromStoreId($model->getStoreId()),
                'storeName' => (string) $this->apsisCoreHelper->getStoreNameFromId($model->getStoreId()),
                'nickname' => (string) $model->getNickname(),
                'reviewTitle' => (string) $model->getTitle(),
                'reviewDetail' => (string) $model->getDetail(),
                'productId' => $this->isProductSet() ? (int) $this->product->getId() : 0,
                'sku' => $this->isProductSet() ? (string) $this->product->getSku() : '',
                'name' => $this->isProductSet() ? (string) $this->product->getName() : '',
                'productUrl' => (string) $this->getProductUrl($model->getStoreId()),
                'productReviewUrl' => (string) $this->getProductReviewUrl($model->getStoreId()),
                'productImageUrl' => (string) $this->getProductImageUrl($model->getStoreId()),
                'catalogPriceAmount' => $this->isProductSet() ?
                    (float) $this->apsisCoreHelper->round($this->product->getPrice()) : 0.00,
                'ratingStarValue' => ($voteCollection->getSize()) ?
                    (int) $voteCollection->getFirstItem()->getValue() : 0
            ];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
