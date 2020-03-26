<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\ApiClient\Client;
use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use \Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Apsis\One\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Apsis\One\Model\ResourceModel\Event\Collection as EventCollection;
use Apsis\One\Model\Event;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResourceModel;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use stdClass;
use Apsis\One\Model\ResourceModel\Event as EventResourceModel;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;

class Events
{
    const LIMIT = 50;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var EventCollectionFactory
     */
    private $eventCollectionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var EventResourceModel
     */
    private $eventResourceModel;

    /**
     * @var AbandonedCollectionFactory
     */
    private $abandonedCollectionFactory;

    /**
     * @var ProfileResourceModel
     */
    private $profileResourceModel;

    /**
     * @var array
     */
    private $eventsDiscriminatorMapping = [
        Event::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE => 'com.apsis1.integrations.magento.events.subscriber-unsubscribe',
        Event::EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER =>
            'com.apsis1.integrations.magento.events.subscriber-register-as-customer',
        Event::EVENT_TYPE_CUSTOMER_LOGIN => 'com.apsis1.integrations.magento.events.login',
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST =>
            'com.apsis1.integrations.magento.events.wishlist-product',
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW => 'com.apsis1.integrations.magento.events.product-review',
        Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART =>
            [
                'main' => 'com.apsis1.integrations.magento.events.abandoned-cart',
                'sub' => 'com.apsis1.integrations.magento.events.abandoned-product'
            ],
        Event::EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER =>
            'com.apsis1.integrations.magento.events.customer-becomes-subscriber',
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER => [
            'main' => 'com.apsis1.integrations.magento.events.order',
            'sub' => 'com.apsis1.integrations.magento.events.order-product'
        ]
    ];

    /**
     * @var array
     */
    private $eventsVersionMapping = [
        'com.apsis1.integrations.magento.events.subscriber-unsubscribe' => false,
        'com.apsis1.integrations.magento.events.subscriber-register-as-customer' => false,
        'com.apsis1.integrations.magento.events.login' => false,
        'com.apsis1.integrations.magento.events.wishlist-product' => false,
        'com.apsis1.integrations.magento.events.product-review' => false,
        'com.apsis1.integrations.magento.events.abandoned-cart' => false,
        'com.apsis1.integrations.magento.events.abandoned-product' => false,
        'com.apsis1.integrations.magento.events.customer-becomes-subscriber' => false,
        'com.apsis1.integrations.magento.events.order' => false,
        'com.apsis1.integrations.magento.events.order-product' => false
    ];

    /**
     * @var string
     */
    private $keySpaceDiscriminator = '';

    /**
     * @var string
     */
    private $sectionDiscriminator = '';

    /**
     * @var string
     */
    private $mappedEmailAttribute = '';

    /**
     * @var array
     */
    private $attributesArrWithVersionId = [];

    /**
     * Profiles constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventCollectionFactory $eventCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EventResourceModel $eventResourceModel
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     * @param ProfileResourceModel $profileResourceModel
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventCollectionFactory $eventCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        EventResourceModel $eventResourceModel,
        AbandonedCollectionFactory $abandonedCollectionFactory,
        ProfileResourceModel $profileResourceModel
    ) {
        $this->profileResourceModel = $profileResourceModel;
        $this->eventResourceModel = $eventResourceModel;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
    }

    /**
     * Sync events
     */
    public function sync()
    {
        $stores = $this->apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $this->sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
            $this->mappedEmailAttribute = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
            );

            if ($this->sectionDiscriminator && $client && $this->mappedEmailAttribute) {
                $this->attributesArrWithVersionId = $this->apsisCoreHelper
                    ->getAttributesArrWithVersionId($client, $this->sectionDiscriminator);
                $this->keySpaceDiscriminator = $this->apsisCoreHelper
                    ->getKeySpaceDiscriminator($this->sectionDiscriminator);
                $this->mapEventVersionIds($client);
                $eventCollection = $this->eventCollectionFactory->create()
                    ->getPendingEventsByStore($store->getId(), self::LIMIT);

                if ($eventCollection->getSize() &&
                    $this->isMinimumEventsMapped() &&
                    $this->mappedEmailAttribute &&
                    isset($this->attributesArrWithVersionId[$this->mappedEmailAttribute])
                ) {
                    $this->processEventCollection($client, $eventCollection, $store);
                }
            }
        }
    }

    /**
     * @return bool
     */
    private function isMinimumEventsMapped()
    {
        foreach ($this->eventsVersionMapping as $mapping) {
            if ($mapping !== false) {
                return true;
            } else {
                continue;
            }
        }
        return false;
    }

    /**
     * @param Client $client
     */
    private function mapEventVersionIds(Client $client)
    {
        $eventDefinition = $client->getEventsTypes($this->sectionDiscriminator);
        if ($eventDefinition && isset($eventDefinition->items)) {
            foreach ($eventDefinition->items as $item) {
                if (! array_key_exists($item->discriminator, $this->eventsVersionMapping)) {
                    continue;
                }

                foreach ($item->versions as $version) {
                    if ($version->deprecated_at === null) {
                        $this->eventsVersionMapping[$item->discriminator] = $version->id;
                        break;
                    }
                }
            }
        } else {
            $this->apsisCoreHelper->log(
                __METHOD__ . ': No event types found on section ' . $this->sectionDiscriminator
            );
            $this->eventsVersionMapping = [];
        }
    }

    /**
     * @param Client $client
     * @param EventCollection $eventCollection
     * @param StoreInterface $store
     */
    private function processEventCollection(Client $client, EventCollection $eventCollection, StoreInterface $store)
    {
        $groupedEvents = $this->getEventsArrayGroupedByProfile($eventCollection);
        foreach ($groupedEvents as $profileEvents) {
            try {
                $profile = $profileEvents['profile'];
                $events = $profileEvents['events'];
                $status = $this->syncProfileForEvent($client, $profile, $store);
                if ($status === false) {
                    $this->apsisCoreHelper->log(
                        __METHOD__ . ': Unable to sync profile for events for Store: ' . $store->getCode() .
                        ' Profile: ' . $profile->getId()
                    );
                    continue;
                } elseif (is_string($status)) {
                    $msg = 'Unable to sync profile with error: ' . $status;
                    $this->eventResourceModel
                        ->updateSyncStatus(array_keys($events), Profile::SYNC_STATUS_FAILED, $msg);
                    continue;
                }

                $groupedEventArray = [];
                foreach ($events as $event) {
                    $eventArray = $this->getEventArr($event);
                    foreach ($eventArray as $eventData) {
                        $groupedEventArray[] = $eventData;
                    }
                }

                if (! empty($groupedEventArray)) {
                    $status = $client->postEventsToProfile(
                        $this->keySpaceDiscriminator,
                        $profile->getIntegrationUid(),
                        $this->sectionDiscriminator,
                        $groupedEventArray
                    );

                    if ($status === false) {
                        $this->apsisCoreHelper->log(
                            __METHOD__ . ': Unable to post events for store ' . $store->getCode() .
                            ' profile ' . $profile->getId()
                        );
                        continue;
                    } elseif (is_string($status)) {
                        $this->eventResourceModel
                            ->updateSyncStatus(array_keys($events), Profile::SYNC_STATUS_FAILED, $status);
                        continue;
                    }

                    $this->eventResourceModel->updateSyncStatus(array_keys($events), Profile::SYNC_STATUS_SYNCED);
                }
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                continue;
            }
        }
    }

    /**
     * @param Event $event
     * @return array
     */
    private function getEventArr(Event $event)
    {
        $eventData = [];
        if ($event->getEventType() == Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART ||
            $event->getEventType() == Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER) {
            $typeArray = $this->eventsDiscriminatorMapping[$event->getEventType()];

            if (empty($this->eventsVersionMapping[$typeArray['main']]) ||
                empty($this->eventsVersionMapping[$typeArray['sub']])
            ) {
                return $eventData;
            }

            $createdAt = $this->apsisCoreHelper->formatDateForPlatformCompatibility($event->getCreatedAt());
            $mainData = (array) $this->apsisCoreHelper->unserialize($event->getEventData());
            $subData = (array) $this->apsisCoreHelper->unserialize($event->getSubEventData());
            $eventData[] = [
                'event_time' => $createdAt,
                'version_id' => $this->eventsVersionMapping[$typeArray['main']],
                'data' => $mainData,
            ];
            foreach ($subData as $item) {
                $eventData[] = [
                    'event_time' => $createdAt,
                    'version_id' => $this->eventsVersionMapping[$typeArray['sub']],
                    'data' => (array) $item,
                ];
            }
        } else {
            if (empty($this->eventsVersionMapping[$this->eventsDiscriminatorMapping[$event->getEventType()]])) {
                return $eventData;
            }

            $eventData[] = [
                'event_time' => $this->apsisCoreHelper->formatDateForPlatformCompatibility($event->getCreatedAt()),
                'version_id' => $this->eventsVersionMapping[$this->eventsDiscriminatorMapping[$event->getEventType()]],
                'data' => (array) $this->apsisCoreHelper->unserialize($event->getEventData()),
            ];
        }

        return $eventData;
    }

    /**
     * @param EventCollection $eventCollection
     *
     * @return array
     */
    private function getEventsArrayGroupedByProfile(EventCollection $eventCollection)
    {
        $profileCollection = $this->profileCollectionFactory->create()
            ->getCollectionFromIds($eventCollection->getColumnValues('profile_id'));
        $groupedEvents = [];
        /** @var Profile $profile */
        foreach ($profileCollection as $profile) {
            $groupedEvents[$profile->getId()]['profile'] = $profile;
        }
        foreach ($eventCollection as $event) {
            $groupedEvents[$event->getProfileId()]['events'][$event->getId()] = $event;
        }
        return $groupedEvents;
    }

    /**
     * @param Client $client
     * @param Profile $profile
     * @param StoreInterface $store
     *
     * @return bool|stdClass|string
     */
    private function syncProfileForEvent(Client $client, Profile $profile, StoreInterface $store)
    {
        $attributesToSync[$this->attributesArrWithVersionId[$this->mappedEmailAttribute]] = $profile->getEmail();
        $mappedAcTokenAttribute = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_AC_TOKEN
        );
        $latestAbandonedCart = $this->abandonedCollectionFactory->create()
            ->loadByProfileIdAndStoreId((int) $profile->getId(), (int) $store->getId());
        if ($mappedAcTokenAttribute &&
            isset($this->attributesArrWithVersionId[$mappedAcTokenAttribute]) &&
            $latestAbandonedCart
        ) {
            $attributesToSync[$this->attributesArrWithVersionId[$mappedAcTokenAttribute]] =
                $latestAbandonedCart->getToken();
        }

        return $client->createProfile(
            $this->keySpaceDiscriminator,
            $profile->getIntegrationUid(),
            $this->sectionDiscriminator,
            $attributesToSync
        );
    }
}
