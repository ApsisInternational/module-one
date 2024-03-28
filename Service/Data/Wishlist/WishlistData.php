<?php

namespace Apsis\One\Service\Data\Wishlist;

use Apsis\One\Service\BaseService;
use Apsis\One\Service\Data\AbstractData;
use Magento\Framework\Model\AbstractModel;

class WishlistData extends AbstractData
{
    /**
     * @inheirtDoc
     */
    public function getDataArr(AbstractModel $model, BaseService $baseService): array
    {
        $this->fetchProduct($model->getProduct());
        return [
            'wishlistId' => $model->getWishlistId(),
            'wishlistItemId' => $model->getId(),
            'websiteName' => $baseService->getStoreWebsiteName($model->getStoreId()),
            'storeName' => $baseService->getStoreName($model->getStoreId()),
            'productId' => $model->getProductId(),
            'sku' => $this->product->getSku(),
            'name' => $this->product->getName(),
            'productUrl' => $this->getProductUrl($model->getStoreId(), $baseService),
            'productImageUrl' => $this->getProductImageUrl($model->getStoreId(), $baseService),
            'catalogPriceAmount' => round($this->product->getPrice(), 2),
            'qty' => $model->getQty(),
            'currencyCode' => $baseService->getStoreCurrency($model->getStoreId()),
        ];
    }
}
