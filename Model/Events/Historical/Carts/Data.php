<?php

namespace Apsis\One\Model\Events\Historical\Carts;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Exception;

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
     * @inheritdoc
     */
    public function getProcessedDataArr(AbstractModel $model, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $product = $this->cartItem->getProduct();
            return [
                'cartId' => (int) $model->getId(),
                'customerId' => (int) $model->getCustomerId(),
                'storeName' => (string) $model->getStore()->getName(),
                'websiteName' => (string) $model->getStore()->getWebsite()->getName(),
                'currencyCode' => (string) $model->getQuoteCurrencyCode(),
                'productId' => (int) $this->cartItem->getProductId(),
                'sku' => (string) $this->cartItem->getSku(),
                'name' => (string) $this->cartItem->getName(),
                'productUrl' => (string) $product->getProductUrl(),
                'productImageUrl' => (string) $this->productServiceProvider->getProductImageUrl($product),
                'qtyOrdered' => (float) $this->cartItem->getQty() ? $this->cartItem->getQty() :
                    ($this->cartItem->getQtyOrdered() ? $this->cartItem->getQtyOrdered() : 1),
                'priceAmount' => $apsisCoreHelper->round($this->cartItem->getPrice()),
                'rowTotalAmount' => $apsisCoreHelper->round($this->cartItem->getRowTotal()),
            ];
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
