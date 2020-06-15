<?php

namespace Apsis\One\Model\Events\Historical\Carts;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

class Data
{
    /**
     * @var ProductServiceProvider
     */
    private $productServiceProvider;

    /**
     * Data constructor.
     *
     * @param ProductServiceProvider $productServiceProvider
     */
    public function __construct(
        ProductServiceProvider $productServiceProvider
    ) {
        $this->productServiceProvider = $productServiceProvider;
    }

    /**
     * @param Quote $cart
     * @param Item $item
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
    public function getDataArr(Quote $cart, Item $item, ApsisCoreHelper $apsisCoreHelper)
    {
        /** @var Product $product */
        $product = $item->getProduct();
        return [
            'cartId' => (int) $cart->getId(),
            'customerId' => (int) $cart->getCustomerId(),
            'storeName' => (string) $cart->getStore()->getName(),
            'websiteName' => (string) $cart->getStore()->getWebsite()->getName(),
            'currencyCode' => (string) $cart->getQuoteCurrencyCode(),
            'productId' => (int) $item->getProductId(),
            'sku' => (string) $item->getSku(),
            'name' => (string) $item->getName(),
            'productUrl' => (string) $product->getProductUrl(),
            'productImageUrl' => (string) $this->productServiceProvider->getProductImageUrl($product),
            'qtyOrdered' => (float) $item->getQty() ? $item->getQty() :
                ($item->getQtyOrdered() ? $item->getQtyOrdered() : 1),
            'priceAmount' => (float) $apsisCoreHelper->round($item->getPrice()),
            'rowTotalAmount' => (float) $apsisCoreHelper->round($item->getRowTotal()),
        ];
    }
}
