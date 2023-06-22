<?php

namespace Apsis\One\Service\Sub;

use Apsis\One\Model\EventModel;
use Apsis\One\Model\ResourceModel\AbandonedResource;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\AbandonedService;
use Apsis\One\Service\BaseService;
use Apsis\One\Service\ProfileService;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Apsis\One\Service\Data\Cart\CartContentDataFactory;
use Apsis\One\Service\Data\Cart\CartContentData;
use Throwable;

class SubAbandonedService
{
    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var AbandonedResource
     */
    private AbandonedResource $abandonedResource;

    /**
     * @var CartContentDataFactory
     */
    private CartContentDataFactory $cartContentDataFactory;

    /**
     * @var ProfileService
     */
    private ProfileService $profileService;

    /**
     * @param CartContentDataFactory $cartContentDataFactory
     * @param AbandonedResource $abandonedResource
     * @param EventResource $eventResource
     * @param ProfileService $profileService
     */
    public function __construct(
        CartContentDataFactory $cartContentDataFactory,
        AbandonedResource $abandonedResource,
        EventResource $eventResource,
        ProfileService $profileService
    ) {
        $this->profileService = $profileService;
        $this->eventResource = $eventResource;
        $this->abandonedResource = $abandonedResource;
        $this->cartContentDataFactory = $cartContentDataFactory;
    }

    /**
     * @return CartContentData
     */
    private function getCartContentDataModel(): CartContentData
    {
        return $this->cartContentDataFactory->create();
    }

    /**
     * @param Collection $quoteCollection
     * @param int $storeId
     * @param AbandonedService $abandonedService
     *
     * @return void
     */
    public function aggregateCartsData(
        Collection $quoteCollection,
        int $storeId,
        AbandonedService $abandonedService
    ): void {
        $abandonedCarts = [];
        $events = [];
        $createdAt = $abandonedService->formatCurrentDateToInternalFormat();

        /** @var Quote $quote */
        foreach ($quoteCollection as $quote) {
            try {
                $profile = $this->profileService
                    ->getProfile(
                        (int) $quote->getStore()->getId(),
                        (string) $quote->getCustomerEmail(),
                        (int) $quote->getCustomerId()
                    );
                $cartData = $this->getCartContentDataModel()
                    ->getCartData($quote, $this->profileService);

                if (! empty($cartData) && ! empty($cartData['items']) && $profile) {
                    $uuid = BaseService::generateUniversallyUniqueIdentifier();
                    $abandonedCarts[] = [
                        'quote_id' => (int) $quote->getId(),
                        'cart_data' => (string) json_encode($cartData),
                        'store_id' => (int) $quote->getStoreId(),
                        'profile_id' => (int) $profile->getId(),
                        'customer_id' => (int) $quote->getCustomerId(),
                        'subscriber_id' => (int) $profile->getSubscriberId(),
                        'email' => (string) $quote->getCustomerEmail(),
                        'token' => (string) $uuid,
                        'created_at' => (string) $createdAt
                    ];
                    $mainData = $this->getDataForEventFromAcData($cartData, $uuid, $abandonedService);
                    if (! empty($mainData)) {
                        $subData = $mainData['items'];
                        unset($mainData['items']);
                        $events[] = [
                            'type' => EventModel::EVENT_TYPE_CUSTOMER_ABANDONED_CART,
                            'event_data' => (string) json_encode($mainData),
                            'sub_event_data' => (string) json_encode($subData),
                            'profile_id' => (int) $profile->getId(),
                            'customer_id' => (int) $quote->getCustomerId(),
                            'subscriber_id' => (int) $profile->getSubscriberId(),
                            'store_id' => (int) $quote->getStoreId(),
                            'email' => (string) $quote->getCustomerEmail(),
                            'sync_status' => EventModel::STATUS_PENDING,
                            'created_at' => (string) $createdAt,
                            'updated_at' => (string) $createdAt,
                        ];
                    }
                }
            } catch (Throwable $e) {
                $abandonedService->logError(__METHOD__, $e);
                continue;
            }
        }

        if (! empty($abandonedCarts)) {
            $result = $this->abandonedResource->insertMultipleItems($abandonedCarts, $abandonedService);
            if ($result && ! empty($events)) {
                $status = $this->eventResource->insertMultipleItems($events, $abandonedService);
                $info = [
                    'Total ACs Inserted' => count($abandonedCarts),
                    'Total AC Events Inserted' => $status,
                    'Store Id' => $storeId
                ];
                $abandonedService->debug(__METHOD__, $info);
            }
        }
    }

    /**
     * @param array $acData
     * @param string $uuid
     * @param AbandonedService $abandonedService
     *
     * @return array
     */
    public function getDataForEventFromAcData(array $acData, string $uuid, AbandonedService $abandonedService): array
    {
        try {
            $items = [];
            foreach ($acData['items'] as $item) {
                $items [] = [
                    'cartId' => (int) $acData['cart_id'],
                    'productId' => (int) $item['product_id'],
                    'sku' => (string) $item['sku'],
                    'name' => (string) $item['name'],
                    'productUrl' => (string) $item['product_url'],
                    'productImageUrl' => (string) $item['product_image_url'],
                    'qtyOrdered' => (float) $item['qty_ordered'],
                    'priceAmount' => (float) $item['price_amount'],
                    'rowTotalAmount' => (float) $item['row_total_amount'],
                ];
            }
            return [
                'cartId' => (int) $acData['cart_id'],
                'customerId' => (int) $acData['customer_info']['customer_id'],
                'storeName' => (string) $acData['store_name'],
                'websiteName' => (string) $acData['website_name'],
                'grandTotalAmount' => (float) $acData['grand_total_amount'],
                'itemsCount' => (float) $acData['items_count'],
                'currencyCode' => (string) $acData['currency_code'],
                'token' => (string) $uuid,
                'items' => $items
            ];
        } catch (Throwable $e) {
            $abandonedService->logError(__METHOD__, $e);
            return [];
        }
    }
}
