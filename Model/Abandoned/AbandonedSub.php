<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Cart\ContentFactory;
use Apsis\One\Model\DateInterval;
use Apsis\One\Model\Event;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\DateIntervalFactory;
use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\DateTimeFactory;
use Apsis\One\Model\DateTimeZoneFactory;

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
     * AbandonedSub constructor.
     *
     * @param ContentFactory $cartContentFactory
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param AbandonedResource $abandonedResource
     * @param DateIntervalFactory $dateIntervalFactory
     * @param EventResource $eventResource
     * @param DateTimeFactory $dateTimeFactory
     * @param DateTimeZoneFactory $dateTimeZoneFactory
     */
    public function __construct(
        ContentFactory $cartContentFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        AbandonedResource $abandonedResource,
        DateIntervalFactory $dateIntervalFactory,
        EventResource $eventResource,
        DateTimeFactory $dateTimeFactory,
        DateTimeZoneFactory $dateTimeZoneFactory
    ) {
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
     * @param Json $jsonSerializer
     * @param string $createdAt
     */
    public function aggregateCartDataFromStoreCollection(
        Collection $quoteCollection,
        ApsisCoreHelper $apsisCoreHelper,
        Json $jsonSerializer,
        string $createdAt
    ) {
        $abandonedCarts = [];
        $events = [];
        foreach ($quoteCollection as $quote) {
            $cartData = $this->cartContentFactory->create()
                ->getCartData($quote);

            if (! empty($cartData)) {
                $token = $apsisCoreHelper->getRandomString();
                $abandonedCarts[] = [
                    'quote_id' => $quote->getId(),
                    'cart_data' => $jsonSerializer->serialize($cartData),
                    'store_id' => $quote->getStoreId(),
                    'customer_id' => $quote->getCustomerId(),
                    'customer_email' => $quote->getCustomerEmail(),
                    'token' => $token,
                    'created_at' => $createdAt
                ];

                $events[] = [
                    'event_type' => Event::EVENT_TYPE_AC,
                    'event_data' => $jsonSerializer->serialize(
                        [
                            'quote_id' => $quote->getId(),
                            'token' => $token
                        ]
                    ),
                    'subscriber_id' => '',
                    'customer_id' => $quote->getCustomerId(),
                    'store_id' => $quote->getStoreId(),
                    'email' => $quote->getCustomerEmail(),
                    'status' => Event::EVENT_STATUS_PENDING,
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
}
