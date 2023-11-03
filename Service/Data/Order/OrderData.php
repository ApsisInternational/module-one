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
            $shopCurrency = $shopName = null;

            /** @var OrderItem $item */
            foreach ($order->getAllVisibleItems() as $item) {
                try {
                    $this->fetchAndSetProductFromItem($item, $baseService);
                    $productDataArr = $this->getCommonProdDataArray($profileModel, $item->getStoreId(), $baseService);
                    if (empty($productDataArr)) {
                        return [];
                    }

                    if (! isset($shopCurrency) || ! isset($shopName)) {
                        $shopCurrency = $productDataArr['shop_currency'];
                        $shopName = $productDataArr['shop_name'];
                    }
                    $productDataArr['product_quantity'] = (float) round($item->getQtyOrdered(), 2);
                    $productDataArr['order_id'] = (string) $order->getEntityId();

                    $items [] = $productDataArr;
                } catch (Throwable $e) {
                    $baseService->logError(__METHOD__, $e);
                    continue;
                }
            }

            if (empty($items)) {
                return [];
            }

            $billingAddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();
            return [
                'profile_id' => (string) $profileModel->getId(),
                'order_id' => (string) $order->getEntityId(),
                'grand_total' => (float) round($order->getGrandTotal(), 2),
                'total_products' => (int) $order->getTotalItemCount(),
                'total_quantity' => (float) $order->getTotalQtyOrdered(),
                'shipping_method_name' => (string) $order->getShippingMethod(),
                'payment_method_name' => (string) $order->getPayment()?->getMethod(),
                'shop_currency' => $shopCurrency,
                'shop_name' => $shopName,
                'shop_id' => (string) $order->getStoreId(),
                'billing_name' => (string) $billingAddress?->getName(),
                'billing_street' => implode(', ', (array) $billingAddress?->getStreet()),
                'billing_postcode' => (string) $billingAddress?->getPostcode(),
                'billing_city' => (string) $billingAddress?->getCity(),
                'billing_region' => (string) $billingAddress?->getRegion(),
                'billing_country' => (string) $billingAddress?->getCountryId(),
                'billing_telephone' => $this->getFormattedPhone(
                    $baseService,
                    (string) $billingAddress?->getCountryId(),
                    (string) $billingAddress?->getTelephone()
                ),
                'shipping_name' => (string) $shippingAddress?->getName(),
                'shipping_street' => implode(', ', (array) $shippingAddress?->getStreet()),
                'shipping_postcode' => (string) $shippingAddress?->getPostcode(),
                'shipping_city' => (string) $shippingAddress?->getCity(),
                'shipping_region' => (string) $shippingAddress?->getRegion(),
                'shipping_country' => (string) $shippingAddress?->getCountryId(),
                'shipping_telephone' => $this->getFormattedPhone(
                    $baseService,
                    (string) $shippingAddress?->getCountryId(),
                    (string) $shippingAddress?->getTelephone()
                ),
                'items' => $items
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
