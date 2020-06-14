<?php

namespace Apsis\One\Model\Events\Historical\Reviews;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\CollectionFactory as VoteCollectionFactory;

class Data
{
    /**
     * @var VoteCollectionFactory
     */
    private $voteCollectionFactory;

    /**
     * Data constructor.
     *
     * @param VoteCollectionFactory $voteCollectionFactory
     */
    public function __construct(VoteCollectionFactory $voteCollectionFactory)
    {
        $this->voteCollectionFactory = $voteCollectionFactory;
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
        $voteCollection = $this->voteCollectionFactory->create()->setReviewFilter($reviewObject->getReviewId());
        $data = [
            'reviewId' => (int) $reviewObject->getReviewId(),
            'customerId' => (int) $reviewObject->getCustomerId(),
            'websiteName' => (string) $apsisCoreHelper->getWebsiteNameFromStoreId($reviewObject->getStoreId()),
            'storeName' => (string) $apsisCoreHelper->getStoreNameFromId(),
            'nickname' => (string) $reviewObject->getNickname(),
            'reviewTitle' => (string) $reviewObject->getTitle(),
            'reviewDetail' => (string) $reviewObject->getDetail(),
            'productId' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'productUrl' => (string) $product->getProductUrl(),
            'productReviewUrl' => (string) $reviewObject->getReviewUrl(),
            'productImageUrl' => (string) $apsisCoreHelper->getProductImageUrl($product),
            'catalogPriceAmount' => (float) $apsisCoreHelper->round($product->getPrice()),
            'ratingStarValue' => ($voteCollection->getSize()) ? (int) $voteCollection->getFirstItem()->getValue() : 0
        ];
        return $data;
    }
}