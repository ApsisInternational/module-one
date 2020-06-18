<?php

namespace Apsis\One\Model\Events\Historical\Carts;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

class Data extends EventData implements EventDataInterface
{
    /**
     * @var Item
     */
    private $cartItem;

    /**
     * @param Quote $cart
     * @param Item $item
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getDataArr(Quote $cart, Item $item, ApsisCoreHelper $apsisCoreHelper)
    {
        $this->cartItem = $item;
        return $this->getProcessedDataArr($cart, $apsisCoreHelper);
    }

    /**
     * @param AbstractModel $cart
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getProcessedDataArr(AbstractModel $cart, ApsisCoreHelper $apsisCoreHelper)
    {
        /** @var Product $product */
        $product = $this->cartItem->getProduct();
        return [
            'cartId' => (int) $cart->getId(),
            'customerId' => (int) $cart->getCustomerId(),
            'storeName' => (string) $cart->getStore()->getName(),
            'websiteName' => (string) $cart->getStore()->getWebsite()->getName(),
            'currencyCode' => (string) $cart->getQuoteCurrencyCode(),
            'productId' => (int) $this->cartItem->getProductId(),
            'sku' => (string) $this->cartItem->getSku(),
            'name' => (string) $this->cartItem->getName(),
            'productUrl' => (string) $product->getProductUrl(),
            'productImageUrl' => (string) $this->productServiceProvider->getProductImageUrl($product),
            'qtyOrdered' => (float) $this->cartItem->getQty() ? $this->cartItem->getQty() :
                ($this->cartItem->getQtyOrdered() ? $this->cartItem->getQtyOrdered() : 1),
            'priceAmount' => (float) $apsisCoreHelper->round($this->cartItem->getPrice()),
            'rowTotalAmount' => (float) $apsisCoreHelper->round($this->cartItem->getRowTotal()),
        ];
    }
}
