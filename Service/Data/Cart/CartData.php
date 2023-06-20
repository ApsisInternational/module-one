<?php

namespace Apsis\One\Service\Data\Cart;

use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Throwable;

class CartData extends AbstractData
{
    /**
     * @param Quote $quote
     * @param Item $item
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getDataArr(Quote $quote, Item $item, BaseService $baseService): array
    {
        try {
            if ($item->getProductId()) {
                $product = $this->loadProduct($item->getProductId(), $quote->getStoreId(), $baseService);
            }

            if (isset($product) && $product instanceof Product) {
                $this->fetchProduct($product, $baseService);
            } else {
                $this->fetchProduct($item, $baseService);
            }

            return [
                'cartId' => (int) $quote->getId(),
                'customerId' => (int) $quote->getCustomerId(),
                'storeName' => (string) $quote->getStore()->getName(),
                'websiteName' => (string) $quote->getStore()->getWebsite()->getName(),
                'currencyCode' => (string) $quote->getQuoteCurrencyCode(),
                'productId' => (int) $item->getProductId(),
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'productUrl' => (string) $this->getProductUrl($quote->getStoreId(), $baseService),
                'productImageUrl' => (string) $this->getProductImageUrl($quote->getStoreId(), $baseService),
                'qtyOrdered' => $item->getQty() ? (float) $item->getQty() :
                    ($item->getQtyOrdered() ? (float) $item->getQtyOrdered() : 1),
                'priceAmount' => (float) round($item->getPrice(), 2),
                'rowTotalAmount' => (float) round($item->getRowTotal(), 2),
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
