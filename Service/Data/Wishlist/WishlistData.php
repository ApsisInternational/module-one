<?php

namespace Apsis\One\Service\Data\Wishlist;

use Apsis\One\Service\BaseService;
use Apsis\One\Service\Data\AbstractData;
use Magento\Catalog\Model\Product;
use Magento\Wishlist\Model\Item as MagentoWishlistItem;
use Magento\Wishlist\Model\Wishlist;
use Throwable;

class WishlistData extends AbstractData
{
    /**
     * @param Wishlist $wishlist
     * @param MagentoWishlistItem $wishlistItem
     * @param int $storeId
     * @param Product $product
     * @param BaseService $service
     *
     * @return array
     */
    public function getWishedData(
        Wishlist $wishlist,
        MagentoWishlistItem $wishlistItem,
        int $storeId,
        Product $product,
        BaseService $service
    ): array {
        try {
            $this->fetchProduct($product, $service);
            return [
                'wishlistId' => (int) $wishlist->getId(),
                'wishlistItemId' => (int) $wishlistItem->getId(),
                'wishlistName' => (string) $wishlist->getName(),
                'customerId' => (int) $wishlist->getCustomerId(),
                'websiteName' => (string) $service->getStoreWebsiteName($storeId),
                'storeName' => (string) $service->getStoreName($storeId),
                'productId' => (int) $wishlistItem->getProductId(),
                'sku' => $this->isProductSet($service) ? (string) $this->product->getSku() : '',
                'name' => $this->isProductSet($service) ? (string) $this->product->getName() : '',
                'productUrl' => (string) $this->getProductUrl($storeId, $service),
                'productImageUrl' => (string) $this->getProductImageUrl($storeId, $service),
                'catalogPriceAmount' => $this->isProductSet($service) ?
                    (float) round($this->product->getPrice(), 2) : 0.00,
                'qty' => (float) $wishlistItem->getQty(),
                'currencyCode' => (string) $service->getStoreCurrency($storeId),
            ];
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return [];
        }
    }
}
