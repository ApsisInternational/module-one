<?php

namespace Apsis\One\Model\Events\Historical\Carts;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Throwable;

class Data extends EventData implements EventDataInterface
{
    /**
     * @var Item
     */
    protected $cartItem;

    /**
     * Data constructor.
     *
     * @param ProductServiceProvider $productServiceProvider
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductServiceProvider $productServiceProvider,
        ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($productServiceProvider, $productRepository);
    }

    /**
     * @param Quote $cart
     * @param Item $item
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param bool $isCron
     *
     * @return array
     */
    public function getDataArr(Quote $cart, Item $item, ApsisCoreHelper $apsisCoreHelper, bool $isCron = false)
    {
        try {
            $this->cartItem = $item;

            if ($isCron && $item->getProductId()) {
                $product = $this->loadProduct($item->getProductId(), $cart->getStoreId());
            }

            if (isset($product) && $product instanceof Product) {
                $this->fetchProduct($product);
            } else {
                $this->fetchProduct($this->cartItem);
            }

            $this->apsisCoreHelper = $apsisCoreHelper;
            return $this->getProcessedDataArr($cart);
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
                'cartId' => (int) $model->getId(),
                'customerId' => (int) $model->getCustomerId(),
                'storeName' => (string) $model->getStore()->getName(),
                'websiteName' => (string) $model->getStore()->getWebsite()->getName(),
                'currencyCode' => (string) $model->getQuoteCurrencyCode(),
                'productId' => (int) $this->cartItem->getProductId(),
                'sku' => (string) $this->cartItem->getSku(),
                'name' => (string) $this->cartItem->getName(),
                'productUrl' => (string) $this->getProductUrl($model->getStoreId()),
                'productImageUrl' => (string) $this->getProductImageUrl($model->getStoreId()),
                'qtyOrdered' => $this->cartItem->getQty() ? (float) $this->cartItem->getQty() :
                    ($this->cartItem->getQtyOrdered() ? (float) $this->cartItem->getQtyOrdered() : 1),
                'priceAmount' => (float) $this->apsisCoreHelper->round($this->cartItem->getPrice()),
                'rowTotalAmount' => (float) $this->apsisCoreHelper->round($this->cartItem->getRowTotal()),
            ];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
