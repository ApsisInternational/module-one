<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\Data\Cart\CartData;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Stdlib\DateTime;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Throwable;

class CartEvents extends AbstractEvents
{
    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param QuoteCollectionFactory $collectionFactory
     * @param CartData $eventData
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        QuoteCollectionFactory $collectionFactory,
        CartData $eventData
    ) {
        parent::__construct($dateTime, $eventResource, $collectionFactory, $eventData);
    }

    /**
     * @inheirtDoc
     */
    public function process(StoreInterface $store, BaseService $baseService, array $profileColArray): void
    {
        $eventsToRegister = $this->findAndRegister($store, $baseService, $profileColArray);
        $this->registerEvents($eventsToRegister, $baseService, $store->getId(), 'Carted');
    }

    /**
     * @inheirtDoc
     */
    protected function getEventsToRegister(
        BaseService $baseService,
        array $entityCollectionArr,
        array $profileCollectionArray,
        StoreInterface $store
    ): array {
        $eventsToRegister = [];
        foreach ($entityCollectionArr as $quote) {
            try {
                $items = $quote->getAllVisibleItems();
                foreach ($items as $item) {
                    try {
                        if (isset($profileCollectionArray[$quote->getCustomerId()]) &&
                            ! empty($eventData = $this->eventData->getDataArr($quote, $item, $baseService))
                        ) {
                            $eventDataForEvent = $this->getFormattedEventDataForRecord(
                                $store->getStoreId(),
                                $profileCollectionArray[$quote->getCustomerId()],
                                EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
                                $item->getCreatedAt(),
                                json_encode($eventData),
                                $baseService
                            );

                            if (! empty($eventDataForEvent)) {
                                $eventsToRegister[] = $eventDataForEvent;
                            }
                        }
                    } catch (Throwable $e) {
                        $baseService->logError(__METHOD__, $e);
                        continue;
                    }
                }
            } catch (Throwable $e) {
                $baseService->logError(__METHOD__, $e);
                continue;
            }
        }
        return $eventsToRegister;
    }
}
