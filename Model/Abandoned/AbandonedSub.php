<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Cart\ContentFactory;
use Apsis\One\Model\DateInterval;
use Apsis\One\Model\Event;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\DateIntervalFactory;
use Apsis\One\Model\Sql\ExpressionFactory;
use Exception;
use Magento\Framework\Stdlib\DateTime;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\DateTimeFactory;
use Apsis\One\Model\DateTimeZoneFactory;
use Apsis\One\Model\Profile;

class AbandonedSub
{
    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * @var DateTimeZoneFactory
     */
    private $dateTimeZoneFactory;

    /**
     * @var DateIntervalFactory
     */
    private $dateIntervalFactory;

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
     * AbandonedSub constructor.
     *
     * @param ContentFactory $cartContentFactory
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param AbandonedResource $abandonedResource
     * @param DateIntervalFactory $dateIntervalFactory
     * @param EventResource $eventResource
     * @param DateTimeFactory $dateTimeFactory
     * @param DateTimeZoneFactory $dateTimeZoneFactory
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     */
    public function __construct(
        ContentFactory $cartContentFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        AbandonedResource $abandonedResource,
        DateIntervalFactory $dateIntervalFactory,
        EventResource $eventResource,
        DateTimeFactory $dateTimeFactory,
        DateTimeZoneFactory $dateTimeZoneFactory,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory
    ) {
        $this->expressionFactory = $expressionFactory;
        $this->dateTime = $dateTime;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->dateTimeZoneFactory = $dateTimeZoneFactory;
        $this->eventResource = $eventResource;
        $this->abandonedResource = $abandonedResource;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->cartContentFactory = $cartContentFactory;
        $this->dateIntervalFactory = $dateIntervalFactory;
    }

    /**
     * @param StoreInterface $store
     * @param string|int $acDelayPeriod
     *
     * @return Collection|boolean
     */
    public function getQuoteCollectionByStore(StoreInterface $store, $acDelayPeriod)
    {
        try {
            $interval = $this->getInterval($acDelayPeriod);
            $fromTime = $this->dateTimeFactory->create(
                [
                    'time' => 'now',
                    'timezone' => $this->dateTimeZoneFactory->create(['timezone' => 'UTC'])
                ]
            );
            $fromTime->sub($interval);
            $toTime = clone $fromTime;
            $fromTime->sub($this->dateIntervalFactory->create(['interval_spec' => 'PT5M']));
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
            return false;
        }
    }

    /**
     * @param string|int $acDelayPeriod
     *
     * @return DateInterval
     */
    private function getInterval($acDelayPeriod)
    {
        $interval = $this->dateIntervalFactory->create(
            ['interval_spec' => sprintf('PT%sM', $acDelayPeriod)]
        );
        return $interval;
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
                ->getCartData($quote);
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
            $result = $this->abandonedResource->insertAbandonedCarts($abandonedCarts);

            if ($result && ! empty($events)) {
                $this->eventResource->insertEvents($events);
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
                'cart_id' => $acData['cart_id'],
                'product_id' => $item['product_id'],
                'sku' => $item['sku'],
                'name' => $item['name'],
                'product_url' => $item['product_url'],
                'product_image_url' => $item['product_image_url'],
                'qty_ordered' => $item['qty_ordered'],
                'price_amount' => $item['price_amount'],
                'row_total_amount' => $item['row_total_amount'],
            ];
        }

        $eventData = [
            'cart_id' => $acData['cart_id'],
            'customer_id' => $acData['customer_info']['customer_id'],
            'created_at' => $acData['created_at'],
            'store_name' => $acData['store_name'],
            'website_name' => $acData['website_name'],
            'grand_total_amount' => $acData['grand_total_amount'],
            'items_count' => $acData['items_count'],
            'currency_code' => $acData['currency_code'],
            'items' => $items
        ];

        return $eventData;
    }
}
