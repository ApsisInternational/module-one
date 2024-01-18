<?php

namespace Apsis\One\Service\Data\Order;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Throwable;

class OrderData extends AbstractData
{
    /**
     * @param Order $order
     * @param ProfileModel $profileModel
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getDataArr(Order $order, ProfileModel $profileModel, BaseService $baseService): array
    {
        try {
            $items = [];
            /** @var OrderItem $item */
            foreach ($order->getAllVisibleItems() as $item) {
                try {
                    $this->fetchAndSetProductFromItem($item, $baseService);
                    $items [] = [
                        'orderId' => (int) $order->getEntityId(),
                        'productId' => (int) $item->getProductId(),
                        'sku' => (string) $item->getSku(),
                        'name' => (string) $item->getName(),
                        'productUrl' => (string) $this->getProductUrl($order->getStoreId(), $baseService),
                        'productImageUrl' => (string) $this->getProductImageUrl($order->getStoreId(), $baseService),
                        'qtyOrdered' => (float) round($item->getQtyOrdered(), 2),
                        'priceAmount' => (float) round($item->getPrice(), 2),
                        'rowTotalAmount' => (float) round($item->getRowTotal(), 2),
                    ];
                } catch (Throwable $e) {
                    $baseService->logError(__METHOD__, $e);
                    continue;
                }
            }

            if (empty($items)) {
                return [];
            }

            return [
                'orderId' => (int) $order->getEntityId(),
                'incrementId' => (string) $order->getIncrementId(),
                'customerId' => (int) $order->getCustomerId(),
                'subscriberId' => (int) $profileModel->getSubscriberId(),
                'isGuest' => (boolean) $order->getCustomerIsGuest(),
                'websiteName' => (string) $order->getStore()->getWebsite()->getName(),
                'storeName' => (string) $order->getStore()->getName(),
                'grandTotalAmount' => (float) round($order->getGrandTotal(), 2),
                'shippingAmount' => (float) round($order->getShippingAmount(), 2),
                'discountAmount' => (float) round($order->getDiscountAmount(), 2),
                'shippingMethodName' => (string) $order->getShippingDescription(),
                'paymentMethodName' => (string) $order->getPayment()->getMethod(),
                'itemsCount' => (int) $order->getTotalItemCount(),
                'currencyCode' => (string) $order->getOrderCurrencyCode(),
                'items' => $items
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
