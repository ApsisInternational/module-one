<?php

namespace Apsis\One\Service\Data\Cart;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
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
    public function getCartedData(Quote $quote, Item $item, BaseService $baseService): array
    {
        try {
            $this->fetchAndSetProductFromItem($item, $baseService);
            return [
                'cartId' => (int) $quote->getId(),
                'customerId' => (int) $quote->getCustomerId(),
                'storeName' => (string) $quote->getStore()->getName(),
                'websiteName' => (string) $quote->getStore()->getWebsite()->getName(),
                'currencyCode' => (string) $quote->getQuoteCurrencyCode(),
                'productId' => $this->isProductSet($baseService) ? (int) $this->product->getId() : 0,
                'sku' => $this->isProductSet($baseService) ? (string) $this->product->getSku() : '',
                'name' => $this->isProductSet($baseService) ? (string) $this->product->getName() : '',
                'productUrl' => (string) $this->getProductUrl($quote->getStoreId(), $baseService),
                'productImageUrl' => (string) $this->getProductImageUrl($quote->getStoreId(), $baseService),
                'qtyOrdered' => $item->getQty() ? (float) $item->getQty() :
                    ($item->getQtyOrdered() ? (float) $item->getQtyOrdered() : 1),
                'priceAmount' => (float) round($item->getPrice(), 2),
                'rowTotalAmount' => (float) round($item->getRowTotal()),
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
