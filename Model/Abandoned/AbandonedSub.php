<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Model\Cart\ContentFactory;
use Apsis\One\Model\Event;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\Stdlib\DateTime;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\EmulationFactory;

class AbandonedSub
{
    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

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
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var EmulationFactory
     */
    private $emulationFactory;

    /**
     * AbandonedSub constructor.
     *
     * @param EmulationFactory $emulationFactory
     * @param ContentFactory $cartContentFactory
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param AbandonedResource $abandonedResource
     * @param EventResource $eventResource
     * @param DateTime $dateTime
     * @param ApsisDateHelper $apsisDateHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param SubscriberFactory $subscriberFactory
     */
    public function __construct(
        EmulationFactory $emulationFactory,
        ContentFactory $cartContentFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        AbandonedResource $abandonedResource,
        EventResource $eventResource,
        DateTime $dateTime,
        ApsisDateHelper $apsisDateHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        SubscriberFactory $subscriberFactory
    ) {
        $this->subscriberFactory = $subscriberFactory;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->dateTime = $dateTime;
        $this->eventResource = $eventResource;
        $this->abandonedResource = $abandonedResource;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->cartContentFactory = $cartContentFactory;
        $this->emulationFactory = $emulationFactory;
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

            return $this->quoteCollectionFactory->create()
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('items_count', ['gt' => 0])
                ->addFieldToFilter('customer_email', ['notnull' => true])
                ->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.updated_at', $updated);
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
        /** @var Quote $quote */
        foreach ($quoteCollection as $quote) {
            try {
                $profile = $this->findProfile($quote, $apsisCoreHelper);
                $cartData = $this->cartContentFactory->create()
                    ->getCartData($quote, $apsisCoreHelper);
                if (! empty($cartData) && ! empty($cartData['items']) && $profile) {
                    $uuid = ApsisCoreHelper::generateUniversallyUniqueIdentifier();
                    $abandonedCarts[] = [
                        'quote_id' => $quote->getId(),
                        'cart_data' => $apsisCoreHelper->serialize($cartData),
                        'store_id' => $quote->getStoreId(),
                        'profile_id' => $profile->getId(),
                        'customer_id' => (int) $quote->getCustomerId(),
                        'customer_email' => $quote->getCustomerEmail(),
                        'token' => $uuid,
                        'created_at' => $createdAt
                    ];
                    $mainData = $this->getDataForEventFromAcData($cartData, $uuid, $apsisCoreHelper);
                    if (! empty($mainData)) {
                        $subData = $mainData['items'];
                        unset($mainData['items']);
                        $events[] = [
                            'event_type' => Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART,
                            'event_data' => $apsisCoreHelper->serialize($mainData),
                            'sub_event_data' => $apsisCoreHelper->serialize($subData),
                            'profile_id' => $profile->getId(),
                            'customer_id' => (int) $quote->getCustomerId(),
                            'store_id' => $quote->getStoreId(),
                            'email' => $quote->getCustomerEmail(),
                            'status' => Profile::SYNC_STATUS_PENDING,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ];
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                continue;
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
     * @param Quote $quote
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return bool|Profile
     */
    private function findProfile(Quote $quote, ApsisCoreHelper $apsisCoreHelper)
    {
        if ($quote->getCustomerId()) {
            $profile = $this->profileCollectionFactory->create()->loadByCustomerId($quote->getCustomerId());
            if ($profile) {
                return $profile;
            }
        }
        $appEmulation = $this->emulationFactory->create();
        try {
            $appEmulation->startEnvironmentEmulation($quote->getStoreId(), Area::AREA_FRONTEND, true);
            $subscriber = $this->subscriberFactory->create()->loadByEmail($quote->getCustomerEmail());
            $appEmulation->stopEnvironmentEmulation();
        } catch (Exception $e) {
            $appEmulation->stopEnvironmentEmulation();
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return false;
        }
        if ($subscriber->getId()) {
            $found = $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getId());
            if ($found) {
                return $found;
            }
        }
        return false;
    }

    /**
     * @param array $acData
     * @param string $uuid
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    private function getDataForEventFromAcData(array $acData, string $uuid, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
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

            return [
                'cartId' => $acData['cart_id'],
                'customerId' => $acData['customer_info']['customer_id'],
                'storeName' => $acData['store_name'],
                'websiteName' => $acData['website_name'],
                'grandTotalAmount' => $acData['grand_total_amount'],
                'itemsCount' => $acData['items_count'],
                'currencyCode' => $acData['currency_code'],
                'token' => $uuid,
                'items' => $items
            ];
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return [];
        }
    }
}
