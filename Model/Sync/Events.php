<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\ApiClient\Client;
use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Apsis\One\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Apsis\One\Model\ResourceModel\Event\Collection as EventCollection;
use Apsis\One\Model\Event;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use stdClass;
use Apsis\One\Model\ResourceModel\Event as EventResourceModel;

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
    private $keySpaceDiscriminator;

    /**
     * @var string
     */
    private $sectionDiscriminator;

    /**
     * Profiles constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventCollectionFactory $eventCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EventResourceModel $eventResourceModel
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventCollectionFactory $eventCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        EventResourceModel $eventResourceModel
    ) {
        $this->eventResourceModel = $eventResourceModel;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventCollectionFactory = $eventCollectionFactory;
    }

    /**
     * Sync events
     */
    public function sync()
    {
        $stores = $this->apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            $this->sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
            if ($account && $this->sectionDiscriminator && $client) {
                $hash = substr(md5($this->sectionDiscriminator), 0, 8);
                $this->keySpaceDiscriminator = "com.apsis1.integrations.keyspaces.$hash.magento";
                $this->mapEventVersionIds($client);
                $eventCollection = $this->eventCollectionFactory->create()
                    ->getPendingEventsByStore($store->getId(), self::LIMIT);

                if ($eventCollection->getSize()) {
                    $this->processEventCollection($client, $eventCollection, $store);
                }
            }
        }
    }

    /**
     * @param Client $client
     */
    private function mapEventVersionIds(Client $client)
    {
        $eventDefinition = $client->getEventsTypes($this->sectionDiscriminator);
        if (is_object($eventDefinition) && isset($eventDefinition->items)) {
            foreach ($eventDefinition->items as $item) {
                if (isset($this->eventsVersionMapping[$item->discriminator])) {
                    foreach ($item->versions as $version) {
                        if ($version->deprecated_at === null) {
                            $this->eventsVersionMapping[$item->discriminator] = $version->id;
                            break;
                        }
                    }
                }
            }
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
            $profile = $profileEvents['profile'];
            $status = $this->syncProfileForEvent($client, $profile, $store);
            if ($status === false) {
                continue;
            }

            $events = $profileEvents['events'];
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

                if ($status !== false) {
                    $this->eventResourceModel->updateSyncStatus(array_keys($events), Profile::SYNC_STATUS_SYNCED);
                }
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
     * @return bool|stdClass
     */
    private function syncProfileForEvent(Client $client, Profile $profile, StoreInterface $store)
    {
        /** @var @toDo update support attribute */
        $mappedEmailAttributeId = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
        );
        if ($mappedEmailAttributeId) {
            return $client->createProfile(
                $this->keySpaceDiscriminator,
                $profile->getIntegrationUid(),
                $this->sectionDiscriminator,
                [$mappedEmailAttributeId => $profile->getEmail()]
            );
        }

        return false;
    }
}
