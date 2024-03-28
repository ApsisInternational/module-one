<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Stdlib\DateTime;
use Apsis\One\Service\Data\Order\OrderData;
use Apsis\One\Model\EventModel;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order;

class OrderEvents extends AbstractEvents
{
    /**
     * @var OrderCollectionFactory
     */
    private OrderCollectionFactory $orderCollectionFactory;

    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param OrderData $entityData
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        OrderData $entityData,
        OrderCollectionFactory $orderCollectionFactory,
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($dateTime, $eventResource, $entityData);
    }

    /**
     * @inheirtDoc
     */
    public function getCollection(int $storeId, array $ids): AbstractCollection
    {
        return $this->orderCollectionFactory
            ->create()
            ->addFieldToFilter('main_table.store_id', $storeId)
            ->addFieldToFilter('main_table.updated_at', $this->fetchDuration)
            ->addFieldToFilter('main_table.customer_email', ['in' => $ids]);
    }

    /**
     * @inheirtDoc
     */
    public function getEventsArr(BaseService $service, array $collection, array $profiles, int $storeId): array
    {
        $events = [];
        /** @var Order $order */
        foreach ($collection as $order) {
            $profile = $profiles[$order->getCustomerEmail()];
            $mainData = $this->entityData->getDataArr($order, $service);
            $items = $mainData['items'];
            unset($mainData['items']);

            $data = ['main' => $mainData, 'sub' => $items];
            $events[] = $this->getDataForInsertion($profile, EventModel::ORDER, $order->getCreatedAt(), $data);
        }
        return $events;
    }
}
