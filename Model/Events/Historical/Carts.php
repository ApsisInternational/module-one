<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Throwable;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Apsis\One\Model\Events\Historical\Carts\Data as CartData;
use Apsis\One\Model\Event;

class Carts extends HistoricalEvent implements EventHistoryInterface
{
    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * Carts constructor.
     *
     * @param DateTime $dateTime
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param EventResource $eventResource
     * @param CartData $cartData
     */
    public function __construct(
        DateTime $dateTime,
        QuoteCollectionFactory $quoteCollectionFactory,
        EventResource $eventResource,
        CartData $cartData
    ) {
        $this->dateTime = $dateTime;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->eventResource = $eventResource;
        $this->eventData = $cartData;
    }

    /**
     * @inheritdoc
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $duration,
        array $profileCollectionArray
    ) {
        try {
            if (empty($profileCollectionArray)) {
                return;
            }

            $quoteCollection = $this->getCartCollection(
                $apsisCoreHelper,
                $store,
                array_keys($profileCollectionArray),
                $duration
            );
            if (empty($quoteCollection)) {
                return;
            }

            $eventsToRegister = $this->getEventsToRegister(
                $apsisCoreHelper,
                $quoteCollection,
                $profileCollectionArray
            );

            $status = $this->registerEvents($eventsToRegister, $apsisCoreHelper);
            if ($status) {
                $info = [
                    'Total Events Inserted' => $status,
                    'Store Id' => $store->getId()
                ];
                $apsisCoreHelper->debug(__METHOD__, $info);
            }

        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param QuoteCollection $quoteCollection
     * @param array $profileCollectionArray
     *
     * @return array
     */
    private function getEventsToRegister(
        ApsisCoreHelper $apsisCoreHelper,
        QuoteCollection $quoteCollection,
        array $profileCollectionArray
    ) {
        $eventsToRegister = [];
        foreach ($quoteCollection as $quote) {
            try {
                $items = $quote->getAllVisibleItems();
                foreach ($items as $item) {
                    try {
                        if (isset($profileCollectionArray[$quote->getCustomerId()]) &&
                            ! empty($eventData = $this->eventData->getDataArr($quote, $item, $apsisCoreHelper))
                        ) {
                            $eventDataForEvent = $this->getEventData(
                                $quote->getStoreId(),
                                $profileCollectionArray[$quote->getCustomerId()],
                                Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
                                $item->getCreatedAt(),
                                $apsisCoreHelper->serialize($eventData),
                                $apsisCoreHelper
                            );

                            if (! empty($eventDataForEvent)) {
                                $eventsToRegister[] = $eventDataForEvent;
                            }
                        }
                    } catch (Throwable $e) {
                        $apsisCoreHelper->logError(__METHOD__, $e);
                        continue;
                    }
                }
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                continue;
            }
        }
        return $eventsToRegister;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     * @param array $customerIds
     * @param array $duration
     *
     * @return array|QuoteCollection
     */
    private function getCartCollection(
        ApsisCoreHelper $apsisCoreHelper,
        StoreInterface $store,
        array $customerIds,
        array $duration
    ) {
        try {
            return $this->quoteCollectionFactory->create()
                ->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.customer_id', ['in' => $customerIds])
                ->addFieldToFilter('main_table.created_at', $duration);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
