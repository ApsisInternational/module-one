<?php

namespace Apsis\One\Service\Data\Review;

use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\Collection;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\CollectionFactory as VoteCollectionFactory;
use Throwable;

class ReviewData extends AbstractData
{
    /**
     * @var VoteCollectionFactory
     */
    private VoteCollectionFactory $voteCollectionFactory;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Image $imageHelper
     * @param VoteCollectionFactory $voteCollectionFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Image $imageHelper,
        VoteCollectionFactory $voteCollectionFactory
    ) {
        parent::__construct($productRepository, $imageHelper);
        $this->voteCollectionFactory = $voteCollectionFactory;
    }

    /**
     * @return Collection
     */
    private function getVoteCollection(): Collection
    {
        return $this->voteCollectionFactory->create();
    }

    /**
     * @param Review $review
     * @param MagentoProduct $product
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getDataArr(Review $review, MagentoProduct $product, BaseService $baseService): array
    {
        try {
            $this->fetchProduct($product, $baseService);
            $voteCollection = $this->getVoteCollection()->setReviewFilter($review->getReviewId());
            return [
                'reviewId' => (int) $review->getReviewId(),
                'customerId' => (int) $review->getCustomerId(),
                'websiteName' => (string) $baseService->getStoreWebsiteName($review->getStoreId()),
                'storeName' => (string) $baseService->getStoreName($review->getStoreId()),
                'nickname' => (string) $review->getNickname(),
                'reviewTitle' => (string) $review->getTitle(),
                'reviewDetail' => (string) $review->getDetail(),
                'productId' => $this->isProductSet($baseService) ? (int) $this->product->getId() : 0,
                'sku' => $this->isProductSet($baseService) ? (string) $this->product->getSku() : '',
                'name' => $this->isProductSet($baseService) ? (string) $this->product->getName() : '',
                'productUrl' => (string) $this->getProductUrl($review->getStoreId(), $baseService),
                'productReviewUrl' => (string) $this->getProductReviewUrl($review->getStoreId(), $baseService),
                'productImageUrl' => (string) $this->getProductImageUrl($review->getStoreId(), $baseService),
                'catalogPriceAmount' => $this->isProductSet($baseService) ?
                    (float) round($this->product->getPrice(), 2) : 0.00,
                'ratingStarValue' => ($voteCollection->getSize()) ?
                    (int) $voteCollection->getFirstItem()->getValue() : 0
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
