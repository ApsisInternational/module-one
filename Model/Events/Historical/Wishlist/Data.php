<?php

namespace Apsis\One\Model\Events\Historical\Wishlist;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\Item as WishlistItem;
use Magento\Wishlist\Model\Wishlist;
use Throwable;

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
        try {
            $this->apsisCoreHelper = $apsisCoreHelper;
            $this->store = $store;
            $this->wishlistItem = $item;
            $this->fetchProduct($product);
            return $this->getProcessedDataArr($wishlist);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function getProcessedDataArr(AbstractModel $model)
    {
        try {
            return [
                'wishlistId' => (int)$model->getId(),
                'wishlistItemId' => (int)$this->wishlistItem->getId(),
                'wishlistName' => (string)$model->getName(),
                'customerId' => (int)$model->getCustomerId(),
                'websiteName' => (string)$this->apsisCoreHelper->getWebsiteNameFromStoreId($this->store->getId()),
                'storeName' => (string)$this->apsisCoreHelper->getStoreNameFromId($this->store->getId()),
                'productId' => (int)$this->wishlistItem->getProductId(),
                'sku' => $this->isProductSet() ? (string) $this->product->getSku() : '',
                'name' => $this->isProductSet() ? (string) $this->product->getName() : '',
                'productUrl' => (string)$this->getProductUrl($this->store->getId()),
                'productImageUrl' => (string)$this->getProductImageUrl($this->store->getId()),
                'catalogPriceAmount' => (float)$this->apsisCoreHelper->round($this->product->getPrice()),
                'qty' => (float)$this->wishlistItem->getQty(),
                'currencyCode' => (string)$this->store->getCurrentCurrencyCode(),
            ];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
