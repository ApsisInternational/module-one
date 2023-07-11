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
        $this->registerEvents($eventsToRegister, $baseService, $store->getId(), 'Order Placed');
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
                    $orderDataArr = $this->eventData->getDataArr(
                        $order,
                        $profileCollectionArray[$order->getCustomerEmail()],
                        $baseService
                    );

                    if (! empty($orderDataArr)) {
                        $items = $orderDataArr['items'];
                        unset($orderDataArr['items']);
                        $eventDataForEvent = $this->getFormattedEventDataForRecord(
                            $store->getStoreId(),
                            $profileCollectionArray[$order->getCustomerEmail()],
                            EventModel::EVENT_PLACED_ORDER,
                            $order->getCreatedAt(),
                            json_encode($orderDataArr),
                            $baseService,
                            json_encode($items)
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
