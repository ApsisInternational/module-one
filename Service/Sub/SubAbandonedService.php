<?php

namespace Apsis\One\Service\Sub;

use Apsis\One\Model\EventModel;
use Apsis\One\Model\ResourceModel\AbandonedResource;
use Apsis\One\Model\ResourceModel\EventResource;
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
     * @param bool $forApi
     *
     * @return array
     */
    public function aggregateCartsData(Collection $quoteCollection, bool $forApi = false): array
    {
        $createdAt = (string) $this->profileService->formatCurrentDateToInternalFormat();
        $acData = [];

        /** @var Quote $quote */
        foreach ($quoteCollection as $quote) {
            try {
                $profile = $this->profileService
                    ->getProfile(
                        (int) $quote->getStore()->getId(),
                        (string) $quote->getCustomerEmail(),
                        (int) $quote->getCustomerId()
                    );
                if (! $profile) {
                    continue;
                }

                $cartData = $this->getCartContentDataModel()->getCartData($quote, $this->profileService);
                if ($forApi) {
                    if (! empty($cartData['cart_content'])) {
                        $cartData['cart_content']['profile_id'] = $profile->getId();
                        $acData[] = $cartData['cart_content'];
                    }
                    continue;
                }

                if (empty($cartData['cart_content']) || empty($cartData['cart_event']) || empty($cartData['token'])) {
                    continue;
                }

                $acData['carts'][] = [
                    'quote_id' => (int) $quote->getId(),
                    'cart_data' => (string) json_encode($cartData['cart_content']),
                    'store_id' => (int) $quote->getStoreId(),
                    'profile_id' => (int) $profile->getId(),
                    'customer_id' => (int) $quote->getCustomerId(),
                    'subscriber_id' => (int) $profile->getSubscriberId(),
                    'email' => (string) $quote->getCustomerEmail(),
                    'token' => $cartData['token'],
                    'created_at' => $createdAt
                ];

                $subEventData = $cartData['cart_event']['items'];
                unset($cartData['cart_event']['items']);
                $acData['events'][] = [
                    'type' => EventModel::EVENT_CART_ABANDONED,
                    'event_data' => (string) json_encode($cartData['cart_event']),
                    'sub_event_data' => (string) json_encode($subEventData),
                    'profile_id' => (int) $profile->getId(),
                    'customer_id' => (int) $quote->getCustomerId(),
                    'subscriber_id' => (int) $profile->getSubscriberId(),
                    'store_id' => (int) $quote->getStoreId(),
                    'email' => (string) $quote->getCustomerEmail(),
                    'sync_status' => EventModel::STATUS_PENDING,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            } catch (Throwable $e) {
                $this->profileService->logError(__METHOD__, $e);
                continue;
            }
        }

        try {
            if (! $forApi && ! empty($acData)) {
                $this->abandonedResource->insertMultipleItems($acData['carts'], $this->profileService);
                $this->eventResource->insertMultipleItems($acData['events'], $this->profileService);
                if (getenv('APSIS_DEVELOPER')) {
                    $info = [
                        'Total ACs Inserted' => count($acData['carts']),
                        'Total AC Events Inserted' => count($acData['events'])
                    ];
                    $this->profileService->debug(__METHOD__, $info);
                }
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }

        return $acData;
    }
}
