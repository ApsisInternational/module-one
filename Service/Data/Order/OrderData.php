<?php

namespace Apsis\One\Service\Data\Order;

use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order\Item as OrderItem;

class OrderData extends AbstractData
{
    /**
     * @inheirtDoc
     */
    public function getDataArr(AbstractModel $model, BaseService $baseService): array
    {
        $items = [];
        /** @var OrderItem $item */
        foreach ($model->getAllVisibleItems() as $item) {
            $this->fetchAndSetProductFromEntity($item, $baseService);
            $items [] = [
                'orderId' => (int) $model->getEntityId(),
                'productId' => (int) $item->getProductId(),
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'productUrl' => $this->getProductUrl($model->getStoreId(), $baseService),
                'productImageUrl' => $this->getProductImageUrl($model->getStoreId(), $baseService),
                'qtyOrdered' => round($item->getQtyOrdered(), 2),
                'priceAmount' => round($item->getPrice(), 2),
                'rowTotalAmount' => round($item->getRowTotal(), 2),
            ];
        }

        return [
            'orderId' => (int) $model->getEntityId(),
            'incrementId' => (string) $model->getIncrementId(),
            'customerId' => (int) $model->getCustomerId(),
            'subscriberId' => 0,
            'isGuest' => (boolean) $model->getCustomerIsGuest(),
            'websiteName' => (string) $model->getStore()->getWebsite()->getName(),
            'storeName' => (string) $model->getStore()->getName(),
            'grandTotalAmount' => round($model->getGrandTotal(), 2),
            'shippingAmount' => round($model->getShippingAmount(), 2),
            'discountAmount' => round($model->getDiscountAmount(), 2),
            'shippingMethodName' => (string) $model->getShippingDescription(),
            'paymentMethodName' => $model->getPayment()->getMethod(),
            'itemsCount' => (int) $model->getTotalItemCount(),
            'currencyCode' => (string) $model->getOrderCurrencyCode(),
            'items' => $items
        ];
    }
}
