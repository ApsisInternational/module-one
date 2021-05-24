<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Events\Historical\Event as HistoricalEvent;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Stdlib\DateTime;
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
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * Orders constructor.
     *
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param OrderData $orderData
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        OrderData $orderData,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->dateTime = $dateTime;
        $this->eventResource = $eventResource;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->eventData = $orderData;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollection $profileCollection
     * @param array $duration
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $duration
    ) {
        if ((boolean) $apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_ORDER
        )) {
            try {
                if (! empty($profileCollectionArray
                        = $this->getFormattedProfileCollection($profileCollection, $apsisCoreHelper)) &&
                    ! empty($orderCollection = $this->getOrderCollection(
                        $apsisCoreHelper,
                        $store,
                        array_keys($profileCollectionArray),
                        $duration
                    ))
                ) {
                    $eventsToRegister = $this->getEventsToRegister(
                        $apsisCoreHelper,
                        $orderCollection,
                        $profileCollectionArray
                    );
                    $status = $this->registerEvents(
                        $eventsToRegister,
                        $apsisCoreHelper,
                        $store,
                        ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_ORDER_HISTORY_DONE_FLAG
                    );

                    $info = [
                        'Total Events Inserted' => $status,
                        'Store Id' => $store->getId()
                    ];
                    $apsisCoreHelper->debug(__METHOD__, $info);
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
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
                    $mainData = $this->eventData->getDataArr(
                        $order,
                        $apsisCoreHelper,
                        (int) $profileCollectionArray[$order->getCustomerEmail()]->getSubscriberId()
                    );
                    if (! empty($mainData) && ! empty($mainData['items'])) {
                        $subData = $mainData['items'];
                        unset($mainData['items']);
                        $eventDataForEvent = $this->getEventData(
                            $order->getStoreId(),
                            $profileCollectionArray[$order->getCustomerEmail()],
                            Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
                            $order->getCreatedAt(),
                            $apsisCoreHelper->serialize($mainData),
                            $apsisCoreHelper,
                            $apsisCoreHelper->serialize($subData)
                        );
                        if (! empty($eventDataForEvent)) {
                            $eventsToRegister[] = $eventDataForEvent;
                        }
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
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
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param ProfileCollection $profileCollection
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    protected function getFormattedProfileCollection(
        ProfileCollection $profileCollection,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        $formattedProfileCollectionArray = [];
        try {
            foreach ($profileCollection as $profile) {
                $formattedProfileCollectionArray[$profile->getEmail()] = $profile;
            }
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $formattedProfileCollectionArray;
    }
}
