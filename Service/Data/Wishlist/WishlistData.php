<?php

namespace Apsis\One\Service\Data\Wishlist;

use Apsis\One\Service\BaseService;
use Apsis\One\Service\Data\AbstractData;
use Magento\Catalog\Model\Product;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\Item as WishlistItem;
use Magento\Wishlist\Model\Wishlist;
use Throwable;

class WishlistData extends AbstractData
{
    /**
     * @param Wishlist $wishlist
     * @param StoreInterface $store
     * @param WishlistItem $wishlistItem
     * @param Product $product
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getDataArr(
        Wishlist $wishlist,
        StoreInterface $store,
        WishlistItem $wishlistItem,
        Product $product,
        BaseService $baseService
    ): array {
        try {
            $this->fetchProduct($product, $baseService);
            return [
                'wishlistId' => (int)$wishlist->getId(),
                'wishlistItemId' => (int)$wishlistItem->getId(),
                'wishlistName' => (string)$wishlist->getName(),
                'customerId' => (int)$wishlist->getCustomerId(),
                'websiteName' => (string)$baseService->getStoreWebsiteName($store->getId()),
                'storeName' => (string)$baseService->getStoreName($store->getId()),
                'productId' => (int)$wishlistItem->getProductId(),
                'sku' => $this->isProductSet($baseService) ? (string) $this->product->getSku() : '',
                'name' => $this->isProductSet($baseService) ? (string) $this->product->getName() : '',
                'productUrl' => (string)$this->getProductUrl($store->getId(), $baseService),
                'productImageUrl' => (string)$this->getProductImageUrl($store->getId(), $baseService),
                'catalogPriceAmount' => (float) round($this->product->getPrice(), 2),
                'qty' => (float)$wishlistItem->getQty(),
                'currencyCode' => (string)$store->getCurrentCurrencyCode(),
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
