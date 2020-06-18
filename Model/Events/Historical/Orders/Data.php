<?php

namespace Apsis\One\Model\Events\Historical\Orders;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

class Data extends EventData implements EventDataInterface
{
    /**
     * @var int
     */
    private $subscriberId;

    /**
     * @param Order $order
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param int $subscriberId
     *
     * @return array
     */
    public function getDataArr(Order $order, ApsisCoreHelper $apsisCoreHelper, int $subscriberId = 0)
    {
        $this->subscriberId = $subscriberId;
        return $this->getProcessedDataArr($order, $apsisCoreHelper);
    }

    /**
     * @param AbstractModel $order
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getProcessedDataArr(AbstractModel $order, ApsisCoreHelper $apsisCoreHelper)
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

        return [
            'orderId' => (int) $order->getEntityId(),
            'incrementId' => (string) $order->getIncrementId(),
            'customerId' => (int) $order->getCustomerId(),
            'subscriberId' => (int) $this->subscriberId,
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
    }
}
