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
     * @param VoteCollectionFactory $voteCollectionFactory
     * @param ProductServiceProvider $productServiceProvider
     */
    public function __construct(
        VoteCollectionFactory $voteCollectionFactory,
        ProductServiceProvider $productServiceProvider
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
        $this->product = $product;
        return $this->getProcessedDataArr($reviewObject, $apsisCoreHelper);
    }

    /**
     * @param AbstractModel $reviewObject
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getProcessedDataArr(AbstractModel $reviewObject, ApsisCoreHelper $apsisCoreHelper)
    {
        $voteCollection = $this->voteCollectionFactory->create()->setReviewFilter($reviewObject->getReviewId());
        return [
            'reviewId' => (int) $reviewObject->getReviewId(),
            'customerId' => (int) $reviewObject->getCustomerId(),
            'websiteName' => (string) $apsisCoreHelper->getWebsiteNameFromStoreId($reviewObject->getStoreId()),
            'storeName' => (string) $apsisCoreHelper->getStoreNameFromId($reviewObject->getStoreId()),
            'nickname' => (string) $reviewObject->getNickname(),
            'reviewTitle' => (string) $reviewObject->getTitle(),
            'reviewDetail' => (string) $reviewObject->getDetail(),
            'productId' => (int) $this->product->getId(),
            'sku' => (string) $this->product->getSku(),
            'name' => (string) $this->product->getName(),
            'productUrl' => (string) $this->product->getProductUrl(),
            'productReviewUrl' => (string) $reviewObject->getReviewUrl(),
            'productImageUrl' => (string) $this->productServiceProvider->getProductImageUrl($this->product),
            'catalogPriceAmount' => $apsisCoreHelper->round($this->product->getPrice()),
            'ratingStarValue' => ($voteCollection->getSize()) ? (int) $voteCollection->getFirstItem()->getValue() : 0
        ];
    }
}
