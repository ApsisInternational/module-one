<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Helper\Date as ApsisDateHelper;
use Apsis\One\Model\Cart\ContentFactory;
use Apsis\One\Model\Event;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Sql\ExpressionFactory;
use Exception;
use Magento\Framework\Stdlib\DateTime;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\Profile;

class AbandonedSub
{
    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var AbandonedResource
     */
    private $abandonedResource;

    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var ContentFactory
     */
    private $cartContentFactory;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ExpressionFactory
     */
    private $expressionFactory;

    /**
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * AbandonedSub constructor.
     *
     * @param ContentFactory $cartContentFactory
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param AbandonedResource $abandonedResource
     * @param EventResource $eventResource
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param ApsisDateHelper $apsisDateHelper
     */
    public function __construct(
        ContentFactory $cartContentFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        AbandonedResource $abandonedResource,
        EventResource $eventResource,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        ApsisDateHelper $apsisDateHelper
    ) {
        $this->apsisDateHelper = $apsisDateHelper;
        $this->expressionFactory = $expressionFactory;
        $this->dateTime = $dateTime;
        $this->eventResource = $eventResource;
        $this->abandonedResource = $abandonedResource;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->cartContentFactory = $cartContentFactory;
    }

    /**
     * @param StoreInterface $store
     * @param string|int $acDelayPeriod
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return Collection|boolean
     */
    public function getQuoteCollectionByStore(StoreInterface $store, $acDelayPeriod, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $interval = $this->apsisDateHelper->getDateIntervalFromIntervalSpec(sprintf('PT%sM', $acDelayPeriod));
            $fromTime = $this->apsisDateHelper->getDateTimeFromTimeAndTimeZone()
                ->sub($interval);
            $toTime = clone $fromTime;
            $fromTime->sub($this->apsisDateHelper->getDateIntervalFromIntervalSpec('PT5M'));
            $updated = [
                'from' => $fromTime->format('Y-m-d H:i:s'),
                'to' => $toTime->format('Y-m-d H:i:s'),
                'date' => true,
            ];

            $quoteCollection = $this->quoteCollectionFactory->create()
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('items_count', ['gt' => 0])
                ->addFieldToFilter('customer_email', ['notnull' => true])
                ->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.updated_at', $updated);
            return $quoteCollection;
        } catch (Exception $e) {
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            return false;
        }
    }

    /**
     * @param Collection $quoteCollection
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function aggregateCartDataFromStoreCollection(Collection $quoteCollection, ApsisCoreHelper $apsisCoreHelper)
    {
        $abandonedCarts = [];
        $events = [];
        $createdAt = $this->dateTime->formatDate(true);
        foreach ($quoteCollection as $quote) {
            $cartData = $this->cartContentFactory->create()
                ->getCartData($quote, $apsisCoreHelper);
            $profile = $apsisCoreHelper->getProfileByEmailAndStoreId($quote->getCustomerEmail(), $quote->getStoreId());

            if (! empty($cartData) && $profile) {
                $abandonedCarts[] = [
                    'quote_id' => $quote->getId(),
                    'cart_data' => $apsisCoreHelper->serialize($cartData),
                    'store_id' => $quote->getStoreId(),
                    'profile_id' => $profile->getId(),
                    'customer_id' => $quote->getCustomerId(),
                    'customer_email' => $quote->getCustomerEmail(),
                    'token' => $this->expressionFactory->create(
                        ["expression" => "(SELECT UUID())"]
                    ),
                    'created_at' => $createdAt
                ];
                $mainData = $this->getDataForEventFromAcData($cartData);
                $subData = $mainData['items'];
                unset($mainData['items']);
                $events[] = [
                    'event_type' => Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART,
                    'event_data' => $apsisCoreHelper->serialize($mainData),
                    'sub_event_data' => $apsisCoreHelper->serialize($subData),
                    'profile_id' => $profile->getId(),
                    'customer_id' => $quote->getCustomerId(),
                    'store_id' => $quote->getStoreId(),
                    'email' => $quote->getCustomerEmail(),
                    'status' => Profile::SYNC_STATUS_PENDING,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

            }
        }

        if (! empty($abandonedCarts)) {
            $result = $this->abandonedResource->insertAbandonedCarts($abandonedCarts, $apsisCoreHelper);

            if ($result && ! empty($events)) {
                $this->eventResource->insertEvents($events, $apsisCoreHelper);
            }
        }
    }

    /**
     * @param array $acData
     *
     * @return array
     */
    private function getDataForEventFromAcData(array $acData)
    {
        $items = [];
        foreach ($acData['items'] as $item) {
            $items [] = [
                'cartId' => $acData['cart_id'],
                'productId' => $item['product_id'],
                'sku' => $item['sku'],
                'name' => $item['name'],
                'productUrl' => $item['product_url'],
                'productImageUrl' => $item['product_image_url'],
                'qtyOrdered' => $item['qty_ordered'],
                'priceAmount' => $item['price_amount'],
                'rowTotalAmount' => $item['row_total_amount'],
            ];
        }

        $eventData = [
            'cartId' => $acData['cart_id'],
            'customerId' => $acData['customer_info']['customer_id'],
            'storeName' => $acData['store_name'],
            'websiteName' => $acData['website_name'],
            'grandTotalAmount' => $acData['grand_total_amount'],
            'itemsCount' => $acData['items_count'],
            'currencyCode' => $acData['currency_code'],
            'items' => $items
        ];

        return $eventData;
    }
}
