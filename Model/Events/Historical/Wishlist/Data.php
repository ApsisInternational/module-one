<?php

namespace Apsis\One\Model\Events\Historical\Wishlist;

use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\Item as WishlistItem;
use Magento\Wishlist\Model\Wishlist;
use Apsis\One\Model\Events\Historical\EventData;
use Exception;

class Data extends EventData implements EventDataInterface
{
    /**
     * @var StoreInterface
     */
    private $store;

    /**
     * @var WishlistItem
     */
    private $wishlistItem;

    /**
     * @var Product
     */
    private $product;

    /**
     * @param Wishlist $wishlist
     * @param StoreInterface $store
     * @param WishlistItem $item
     * @param Product $product
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getDataArr(
        Wishlist $wishlist,
        StoreInterface $store,
        WishlistItem $item,
        Product $product,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        $this->store = $wishlist->getStore();
        $this->wishlistItem = $item;
        $this->product = $product;
        return $this->getProcessedDataArr($wishlist, $apsisCoreHelper);
    }

    /**
     * @param AbstractModel $wishlist
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getProcessedDataArr(AbstractModel $wishlist, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            return [
                'wishlistId' => (int)$wishlist->getId(),
                'wishlistItemId' => (int)$this->wishlistItem->getId(),
                'wishlistName' => (string)$wishlist->getName(),
                'customerId' => (int)$wishlist->getCustomerId(),
                'websiteName' => (string)$apsisCoreHelper->getWebsiteNameFromStoreId($this->store->getId()),
                'storeName' => (string)$apsisCoreHelper->getStoreNameFromId($this->store->getId()),
                'productId' => (int)$this->product->getId(),
                'sku' => (string)$this->product->getSku(),
                'name' => (string)$this->product->getName(),
                'productUrl' => (string)$this->product->getProductUrl(),
                'productImageUrl' => (string)$this->productServiceProvider->getProductImageUrl($this->product),
                'catalogPriceAmount' => $apsisCoreHelper->round($this->product->getPrice()),
                'qty' => (float)$this->wishlistItem->getQty(),
                'currencyCode' => (string)$this->store->getCurrentCurrencyCode(),
            ];
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
