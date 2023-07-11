<?php

namespace Apsis\One\Service\Data\Review;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
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
     * @param CollectionFactory $categoryCollection
     * @param VoteCollectionFactory $voteCollectionFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Image $imageHelper,
        CollectionFactory $categoryCollection,
        VoteCollectionFactory $voteCollectionFactory
    ) {
        parent::__construct($productRepository, $imageHelper, $categoryCollection);
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
     * @param ProfileModel $profile
     * @param Review $review
     * @param MagentoProduct $product
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getReviewData(
        ProfileModel $profile,
        Review $review,
        MagentoProduct $product,
        BaseService $baseService
    ): array {
        try {
            $this->fetchProduct($product, $baseService);
            $voteCollection = $this->getVoteCollection()->setReviewFilter($review->getReviewId());
            $commonDataArray = $this->getCommonProdDataArray($profile, $review->getStoreId(), $baseService);
            if (empty($commonDataArray)) {
                return [];
            }

            return array_merge(
                $commonDataArray,
                [
                    'review_rating' => $this->getTranslatedRating($voteCollection, $baseService),
                    'review_text' => (string) $review->getDetail()
                ]
            );
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param Collection $voteCollection
     * @param BaseService $baseService
     *
     * @return int
     */
    private function getTranslatedRating(Collection $voteCollection, BaseService $baseService): int
    {
        $rating = 0;
        try {
            if ($voteCollection->getSize()) {
                $rating = (int) $voteCollection->getFirstItem()->getValue();
                if ($rating > 0) {
                    $rating *= 20;
                }
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return $rating;
    }
}
