<?php

namespace Apsis\One\Service\Data\Review;

use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\Collection;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\CollectionFactory as VoteCollectionFactory;
use Magento\Review\Model\ResourceModel\Rating\Option\CollectionFactory as RatingOptionCollectionFactory;
use Throwable;

class ReviewData extends AbstractData
{
    /**
     * @var VoteCollectionFactory
     */
    private VoteCollectionFactory $voteCollectionFactory;

    /**
     * @var RatingOptionCollectionFactory
     */
    private RatingOptionCollectionFactory $ratingOptionCollectionFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Image $imageHelper
     * @param CollectionFactory $categoryCollection
     * @param VoteCollectionFactory $voteCollectionFactory
     * @param RatingOptionCollectionFactory $ratingOptionCollectionFactory
     * @param RequestInterface $request
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Image $imageHelper,
        CollectionFactory $categoryCollection,
        VoteCollectionFactory $voteCollectionFactory,
        RatingOptionCollectionFactory $ratingOptionCollectionFactory,
        RequestInterface $request
    ) {
        parent::__construct($productRepository, $imageHelper, $categoryCollection);
        $this->voteCollectionFactory = $voteCollectionFactory;
        $this->ratingOptionCollectionFactory = $ratingOptionCollectionFactory;
        $this->request = $request;
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
    public function getReviewData(
        Review $review,
        MagentoProduct $product,
        BaseService $baseService
    ): array {
        try {
            $this->fetchProduct($product, $baseService);
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
                'productReviewUrl' => (string) $this->getProductUrl($review->getStoreId(), $baseService),
                'productImageUrl' => (string) $this->getProductImageUrl($review->getStoreId(), $baseService),
                'catalogPriceAmount' => $this->isProductSet($baseService) ?
                    (float) round($this->product->getPrice(), 2) : 0.00,
                'ratingStarValue' => $this->getVoteValue($review)
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param Review $review
     *
     * @return int
     */
    private function getVoteValue(Review $review): int
    {
        $voteCollection = $this->getVoteCollection()->setReviewFilter($review->getReviewId());
        if ($voteCollection->getSize()) {
            return (int) $voteCollection->getFirstItem()->getValue();
        }

        $rating = $this->request->getParam('ratings', []);
        if (empty($rating)) {
            return 0;
        }


        foreach ($rating as $ratingId => $optionId) {
            $ratingOptionCollection = $this->ratingOptionCollectionFactory
                ->create()
                ->addFilter('rating_id', $ratingId)
                ->addFilter('option_id', $optionId);
            if ($ratingOptionCollection->getSize()) {
                return (int) $ratingOptionCollection->getFirstItem()->getValue();
            }
        }
        return 0;
    }
}
