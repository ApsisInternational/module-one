<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\Events\Historical\Orders\Data as OrderData;
use Exception;
use Apsis\One\Model\Event;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\Order;

class Orders extends HistoricalEvent implements EventHistoryInterface
{
    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var OrderData
     */
    private $orderData;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * Orders constructor.
     *
     * @param EventResource $eventResource
     * @param OrderData $orderData
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        EventResource $eventResource,
        OrderData $orderData,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->eventResource = $eventResource;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderData = $orderData;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $profileCollectionArray
     * @param array $duration
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        array $profileCollectionArray,
        array $duration
    ) {
        if ((boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_ORDER
        )) {
            try {
                $profileCollectionArray = $this->getFormattedProfileCollection($profileCollectionArray);
                if (! empty($orderCollection = $this->getOrderCollection(
                    $apsisCoreHelper,
                    $store,
                    array_keys($profileCollectionArray),
                    $duration
                ))) {
                    $eventsToRegister = $this->getEventsToRegister(
                        $apsisCoreHelper,
                        $orderCollection,
                        $profileCollectionArray
                    );
                    if (! empty($eventsToRegister)) {
                        $this->eventResource->insertEvents($eventsToRegister, $apsisCoreHelper);
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            }
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param OrderCollection $orderCollection
     * @param array $profileCollectionArray
     *
     * @return array
     */
    private function getEventsToRegister(
        ApsisCoreHelper $apsisCoreHelper,
        OrderCollection $orderCollection,
        array $profileCollectionArray
    ) {
        $eventsToRegister = [];
        /** @var Order $order */
        foreach ($orderCollection as $order) {
            try {
                if (isset($profileCollectionArray[$order->getCustomerEmail()])) {
                    $mainData = $this->orderData->getDataArr(
                        $order,
                        $apsisCoreHelper,
                        (int) $profileCollectionArray[$order->getCustomerEmail()]->getSubscriberId()
                    );
                    $subData = $mainData['items'];
                    unset($mainData['items']);
                    $eventsToRegister[] = $this->getEventData(
                        $profileCollectionArray[$order->getCustomerEmail()],
                        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
                        $order->getCreatedAt(),
                        $apsisCoreHelper->serialize($mainData),
                        $apsisCoreHelper->serialize($subData)
                    );
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                continue;
            }
        }
        return $eventsToRegister;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param StoreInterface $store
     * @param array $emails
     * @param array $duration
     *
     * @return array|OrderCollection
     */
    private function getOrderCollection(
        ApsisCoreHelper $apsisCoreHelper,
        StoreInterface $store,
        array $emails,
        array $duration
    ) {
        try {
            return $this->orderCollectionFactory->create()
                ->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.customer_email', ['in' => $emails])
                ->addFieldToFilter('main_table.created_at', $duration);
        } catch (Exception $e) {
            $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return [];
        }
    }

    /**
     * @param array $profileCollectionArray
     *
     * @return array
     */
    private function getFormattedProfileCollection(array $profileCollectionArray)
    {
        $formattedProfileCollectionArray = [];
        foreach ($profileCollectionArray as $profile) {
            $formattedProfileCollectionArray[$profile->getEmail()] = $profile;
        }
        return $formattedProfileCollectionArray;
    }
}
