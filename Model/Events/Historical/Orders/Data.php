<?php

namespace Apsis\One\Model\Events\Historical\Orders;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

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
     * @param Order $order
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param int $subscriberId
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
    public function getDataArr(Order $order, ApsisCoreHelper $apsisCoreHelper, $subscriberId = 0)
    {
        $items = [];
        /** @var Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $items [] = [
                'orderId' => (int) $order->getEntityId(),
                'productId' => (int) $item->getProductId(),
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'productUrl' => (string) $product->getProductUrl(),
                'productImageUrl' => (string) $this->productServiceProvider->getProductImageUrl($product),
                'qtyOrdered' => (float) $apsisCoreHelper->round($item->getQtyOrdered()),
                'priceAmount' => (float) $apsisCoreHelper->round($item->getPrice()),
                'rowTotalAmount' => (float) $apsisCoreHelper->round($item->getRowTotal()),
            ];
        }

        $data = [
            'orderId' => (int) $order->getEntityId(),
            'incrementId' => (string) $order->getIncrementId(),
            'customerId' => (int) $order->getCustomerId(),
            'subscriberId' => (int) $subscriberId,
            'isGuest' => (boolean) $order->getCustomerIsGuest(),
            'websiteName' => (string) $order->getStore()->getWebsite()->getName(),
            'storeName' => (string) $order->getStore()->getName(),
            'grandTotalAmount' => (float) $apsisCoreHelper->round($order->getGrandTotal()),
            'shippingAmount' => (float) $apsisCoreHelper->round($order->getShippingAmount()),
            'discountAmount' => (float) $apsisCoreHelper->round($order->getDiscountAmount()),
            'shippingMethodName' => (string) $order->getShippingDescription(),
            'paymentMethodName' => (string) $order->getPayment()->getMethod(),
            'itemsCount' => (int) $order->getTotalItemCount(),
            'currencyCode' => (string) $order->getOrderCurrencyCode(),
            'items' => $items
        ];
        return $data;
    }
}
