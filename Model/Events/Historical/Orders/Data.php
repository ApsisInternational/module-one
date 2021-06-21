<?php

namespace Apsis\One\Model\Events\Historical\Orders;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Throwable;

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
        try {
            $this->subscriberId = $subscriberId;
            return $this->getProcessedDataArr($order, $apsisCoreHelper);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getProcessedDataArr(AbstractModel $model, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $items = [];
            foreach ($model->getAllVisibleItems() as $item) {
                try {
                    $product = $item->getProduct();
                    $items [] = [
                        'orderId' => (int) $model->getEntityId(),
                        'productId' => (int) $item->getProductId(),
                        'sku' => (string) $item->getSku(),
                        'name' => (string) $item->getName(),
                        'productUrl' => ($product && $product->getId())? (string) $product->getProductUrl() : '',
                        'productImageUrl' => ($product && $product->getId())?
                            (string) $this->productServiceProvider->getProductImageUrl($product) : '',
                        'qtyOrdered' => $apsisCoreHelper->round($item->getQtyOrdered()),
                        'priceAmount' => $apsisCoreHelper->round($item->getPrice()),
                        'rowTotalAmount' => $apsisCoreHelper->round($item->getRowTotal()),
                    ];
                } catch (Throwable $e) {
                    $apsisCoreHelper->logError(__METHOD__, $e);
                    continue;
                }
            }

            return [
                'orderId' => (int) $model->getEntityId(),
                'incrementId' => (string) $model->getIncrementId(),
                'customerId' => (int) $model->getCustomerId(),
                'subscriberId' => (int) $this->subscriberId,
                'isGuest' => (boolean) $model->getCustomerIsGuest(),
                'websiteName' => (string) $model->getStore()->getWebsite()->getName(),
                'storeName' => (string) $model->getStore()->getName(),
                'grandTotalAmount' => $apsisCoreHelper->round($model->getGrandTotal()),
                'shippingAmount' => $apsisCoreHelper->round($model->getShippingAmount()),
                'discountAmount' => $apsisCoreHelper->round($model->getDiscountAmount()),
                'shippingMethodName' => (string) $model->getShippingDescription(),
                'paymentMethodName' => (string) $model->getPayment()->getMethod(),
                'itemsCount' => (int) $model->getTotalItemCount(),
                'currencyCode' => (string) $model->getOrderCurrencyCode(),
                'items' => $items
            ];
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
