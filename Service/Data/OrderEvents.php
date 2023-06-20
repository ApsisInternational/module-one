<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Stdlib\DateTime;
use Apsis\One\Service\Data\Order\OrderData;
use Apsis\One\Model\EventModel;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Throwable;

class OrderEvents extends AbstractEvents
{
    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param OrderCollectionFactory $collectionFactory
     * @param OrderData $eventData
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        OrderCollectionFactory $collectionFactory,
        OrderData $eventData
    ) {
        parent::__construct($dateTime, $eventResource, $collectionFactory, $eventData);
    }

    /**
     * @inheirtDoc
     */
    public function process(StoreInterface $store, BaseService $baseService, array $profileColArray): void
    {
        $eventsToRegister = $this->findAndRegister($store, $baseService, $profileColArray);
        $this->registerEvents($eventsToRegister, $baseService, $store->getId(), 'Order');
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

        /** @var Order $order */
        foreach ($entityCollectionArr as $order) {
            try {
                if (isset($profileCollectionArray[$order->getCustomerEmail()])) {
                    $mainData = $this->eventData->getDataArr(
                        $order,
                        $baseService,
                        (int) $profileCollectionArray[$order->getCustomerEmail()]->getSubscriberId()
                    );

                    if (! empty($mainData) && ! empty($mainData['items'])) {
                        $subData = $mainData['items'];
                        unset($mainData['items']);

                        $eventDataForEvent = $this->getFormattedEventDataForRecord(
                            $store->getStoreId(),
                            $profileCollectionArray[$order->getCustomerEmail()],
                            EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
                            $order->getCreatedAt(),
                            json_encode($mainData),
                            $baseService,
                            json_encode($subData)
                        );

                        if (! empty($eventDataForEvent)) {
                            $eventsToRegister[] = $eventDataForEvent;
                        }
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
