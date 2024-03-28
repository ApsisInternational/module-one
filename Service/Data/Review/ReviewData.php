<?php

namespace Apsis\One\Service\Data\Review;

use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Framework\Model\AbstractModel;

class ReviewData extends AbstractData
{
    /**
     * @inheirtDoc
     */
    public function getDataArr(AbstractModel $model, BaseService $baseService): array
    {
        $this->fetchAndSetProductFromEntity($model, $baseService);
        return [
            'reviewId' => $model->getReviewId(),
            'customerId' => $model->getCustomerId(),
            'websiteName' => $baseService->getStoreWebsiteName($model->getStoreId()),
            'storeName' => $baseService->getStoreName($model->getStoreId()),
            'nickname' => $model->getNickname(),
            'reviewTitle' => $model->getTitle(),
            'reviewDetail' => $model->getDetail(),
            'productId' => $this->product->getId(),
            'sku' => $this->product->getSku(),
            'name' => $this->product->getName(),
            'productUrl' => $this->getProductUrl($model->getStoreId(), $baseService),
            'productReviewUrl' => $this->getProductUrl($model->getStoreId(), $baseService),
            'productImageUrl' => $this->getProductImageUrl($model->getStoreId(), $baseService),
            'catalogPriceAmount' => round($this->product->getPrice(), 2)
        ];
    }
}
