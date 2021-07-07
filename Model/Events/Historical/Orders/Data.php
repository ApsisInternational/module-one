<?php

namespace Apsis\One\Model\Events\Historical\Orders;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Events\Historical\EventDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order;
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
            $this->apsisCoreHelper = $apsisCoreHelper;
            $this->subscriberId = $subscriberId;
            return $this->getProcessedDataArr($order);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function getProcessedDataArr(AbstractModel $model)
    {
        try {
            $items = [];
            foreach ($model->getAllVisibleItems() as $item) {
                try {
                    $this->fetchProduct($item);
                    $items [] = [
                        'orderId' => (int) $model->getEntityId(),
                        'productId' => (int) $item->getProductId(),
                        'sku' => (string) $item->getSku(),
                        'name' => (string) $item->getName(),
                        'productUrl' => (string) $this->getProductUrl($model->getStoreId()),
                        'productImageUrl' => (string) $this->getProductImageUrl($model->getStoreId()),
                        'qtyOrdered' => (float) $this->apsisCoreHelper->round($item->getQtyOrdered()),
                        'priceAmount' => (float) $this->apsisCoreHelper->round($item->getPrice()),
                        'rowTotalAmount' => (float) $this->apsisCoreHelper->round($item->getRowTotal()),
                    ];
                } catch (Throwable $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
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
                'grandTotalAmount' => $this->apsisCoreHelper->round($model->getGrandTotal()),
                'shippingAmount' => $this->apsisCoreHelper->round($model->getShippingAmount()),
                'discountAmount' => $this->apsisCoreHelper->round($model->getDiscountAmount()),
                'shippingMethodName' => (string) $model->getShippingDescription(),
                'paymentMethodName' => (string) $model->getPayment()->getMethod(),
                'itemsCount' => (int) $model->getTotalItemCount(),
                'currencyCode' => (string) $model->getOrderCurrencyCode(),
                'items' => $items
            ];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
