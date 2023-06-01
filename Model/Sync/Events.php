<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\ApiClient\Client;
use Apsis\One\Model\Event;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResourceModel;
use Apsis\One\Model\ResourceModel\Event\Collection as EventCollection;
use Apsis\One\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Magento\Store\Api\Data\StoreInterface;
use stdClass;
use Throwable;

class Events
{
    /**
     * Maximum collection limit per store
     */
    const COLLECTION_LIMIT = 1000;
    /**
     * Maximum event limit per profile
     */
    const PROFILE_EVENT_LIMIT = 100;

    /**
     * Event discriminators
     */
    const UNSUBSCRIBED_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.subscriber-unsubscribe';
    const SUBSCRIBER_2_CUSTOMER_EVENT_DISCRIMINATOR =
        'com.apsis1.integrations.magento.events.subscriber-register-as-customer';
    const CUSTOMER_LOGIN_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.login';
    const WISHLIST_PRODUCT_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.wishlist-product';
    const PRODUCT_REVIEW_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.product-review';
    const AC_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.abandoned-cart';
    const AC_PRODUCT_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.abandoned-product';
    const CUSTOMER_2_SUBSCRIBER_EVENT_DISCRIMINATOR =
        'com.apsis1.integrations.magento.events.customer-becomes-subscriber';
    const ORDER_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.order';
    const ORDER_PRODUCT_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.order-product';
    const PRODUCT_CARTED_EVENT_DISCRIMINATOR = 'com.apsis1.integrations.magento.events.product-carted';

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var EventCollectionFactory
     */
    private EventCollectionFactory $eventCollectionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var EventResourceModel
     */
    private EventResourceModel $eventResourceModel;

    /**
     * @var ApsisDateHelper
     */
    private ApsisDateHelper $apsisDateHelper;

    /**
     * @var array
     */
    private array $eventsDiscriminatorMapping = [
        Event::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE => self::UNSUBSCRIBED_EVENT_DISCRIMINATOR,
        Event::EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER => self::SUBSCRIBER_2_CUSTOMER_EVENT_DISCRIMINATOR,
        Event::EVENT_TYPE_CUSTOMER_LOGIN => self::CUSTOMER_LOGIN_EVENT_DISCRIMINATOR,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST => self::WISHLIST_PRODUCT_EVENT_DISCRIMINATOR,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW => self::PRODUCT_REVIEW_EVENT_DISCRIMINATOR,
        Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART => [
            'main' => self::AC_EVENT_DISCRIMINATOR,
            'sub' => self::AC_PRODUCT_EVENT_DISCRIMINATOR
        ],
        Event::EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER => self::CUSTOMER_2_SUBSCRIBER_EVENT_DISCRIMINATOR,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER => [
            'main' => self::ORDER_EVENT_DISCRIMINATOR,
            'sub' => self::ORDER_PRODUCT_EVENT_DISCRIMINATOR
        ],
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART => self::PRODUCT_CARTED_EVENT_DISCRIMINATOR,
    ];

    /**
     * @var array
     */
    private array $eventsVersionMapping = [
        self::UNSUBSCRIBED_EVENT_DISCRIMINATOR => false,
        self::SUBSCRIBER_2_CUSTOMER_EVENT_DISCRIMINATOR => false,
        self::CUSTOMER_LOGIN_EVENT_DISCRIMINATOR => false,
        self::WISHLIST_PRODUCT_EVENT_DISCRIMINATOR => false,
        self::PRODUCT_REVIEW_EVENT_DISCRIMINATOR => false,
        self::AC_EVENT_DISCRIMINATOR => false,
        self::AC_PRODUCT_EVENT_DISCRIMINATOR => false,
        self::CUSTOMER_2_SUBSCRIBER_EVENT_DISCRIMINATOR => false,
        self::ORDER_EVENT_DISCRIMINATOR => false,
        self::ORDER_PRODUCT_EVENT_DISCRIMINATOR => false,
        self::PRODUCT_CARTED_EVENT_DISCRIMINATOR => false
    ];

    /**
     * @var string
     */
    private string $keySpace;

    /**
     * @var string
     */
    private string $section;

    /**
     * @var array
     */
    private array $attributeVerIds = [];

    /**
     * Profiles constructor.
     *
     * @param EventCollectionFactory $eventCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EventResourceModel $eventResourceModel
     * @param ApsisDateHelper $apsisDateHelper
     */
    public function __construct(
        EventCollectionFactory $eventCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        EventResourceModel $eventResourceModel,
        ApsisDateHelper $apsisDateHelper
    ) {
        $this->apsisDateHelper = $apsisDateHelper;
        $this->eventResourceModel = $eventResourceModel;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->eventCollectionFactory = $eventCollectionFactory;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function process(ApsisCoreHelper $apsisCoreHelper): void
    {
        $this->apsisCoreHelper = $apsisCoreHelper;

        foreach ($this->apsisCoreHelper->getStores() as $store) {
            try {
                $this->section = (string) $this->apsisCoreHelper
                    ->getStoreConfig($store, ApsisCoreHelper::PATH_APSIS_CONFIG_SECTION);
                $this->keySpace = (string) $this->apsisCoreHelper
                    ->getStoreConfig($store, ApsisCoreHelper::PATH_APSIS_CONFIG_PROFILE_KEY);
                $client = $this->apsisCoreHelper->getApiClient($store);

                // Validate all things compulsory
                if (! $this->section || ! $this->keySpace || ! $client) {
                    continue;
                }

                $eventCollection = $this->eventCollectionFactory->create()
                    ->getPendingEventsByStore($store->getId(), self::COLLECTION_LIMIT);
                if (! $eventCollection->getSize()) {
                    continue;
                }

                // Validate we have all necessary attribute version ids
                $this->attributeVerIds = $this->apsisCoreHelper->getAttributeVersionIds($client, $this->section);
                if (empty($this->attributeVerIds) ||
                    ! isset($this->attributeVerIds[ApsisCoreHelper::EMAIL_DISCRIMINATOR])
                ) {
                    continue;
                }

                // Validate we have all necessary event version ids
                $this->mapEventVersionIds($client);
                if (empty($this->eventsVersionMapping) || ! $this->isMinimumEventsMapped()) {
                    continue;
                }

                //At this point proceed for actual batching of events to sync
                $this->processEventCollection($client, $eventCollection, $store);
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
                continue;
            }
        }
    }

    /**
     * @return bool
     */
    private function isMinimumEventsMapped(): bool
    {
        foreach ($this->eventsVersionMapping as $mapping) {
            if ($mapping !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Client $client
     *
     * @return void
     */
    private function mapEventVersionIds(Client $client): void
    {
        $eventDefinition = $client->getEvents($this->section);
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
            $this->eventsVersionMapping = [];
        }
    }

    /**
     * @param Client $client
     * @param EventCollection $eventCollection
     * @param StoreInterface $store
     *
     * @return void
     */
    private function processEventCollection(
        Client $client,
        EventCollection $eventCollection,
        StoreInterface $store
    ): void {
        $groupedEvents = $this->getEventsArrayGroupedByProfile($eventCollection);
        foreach ($groupedEvents as $profileEvents) {
            try {
                /** @var Profile $profile */
                $profile = $profileEvents['profile'];
                $events = $profileEvents['events'];
                if (empty($events)) {
                    continue;
                }

                $status = $this->syncProfileForEvent($client, $profile);
                if ($status === false) {
                    $this->apsisCoreHelper->log(
                        __METHOD__ . ': Unable to sync profile for events for Store: ' . $store->getCode() .
                        ' Profile: ' . $profile->getId()
                    );
                    continue;
                } elseif (is_string($status)) {
                    $msg = 'Unable to sync profile with error: ' . $status;
                    $this->eventResourceModel
                        ->updateSyncStatus(
                            array_keys($events),
                            Event::STATUS_FAILED,
                            $this->apsisCoreHelper,
                            $msg
                        );
                    continue;
                }

                $groupedEventArray = [];
                foreach ($events as $event) {
                    $eventArray = $this->getEventArr($event);
                    foreach ($eventArray as $eventData) {
                        $groupedEventArray[] = $eventData;
                    }
                }
                if (empty($groupedEventArray)) {
                    continue;
                }

                $status = $client->addEventsToProfile(
                    $this->keySpace,
                    $profile->getId(),
                    $this->section,
                    $groupedEventArray
                );

                if ($status === false) {
                    $this->apsisCoreHelper->log(
                        __METHOD__ . ': Unable to post events for store id ' . $store->getId() .
                        ' profile ' . $profile->getId()
                    );
                    continue;
                } elseif (is_string($status)) {
                    $this->eventResourceModel
                        ->updateSyncStatus(
                            array_keys($events),
                            Event::STATUS_FAILED,
                            $this->apsisCoreHelper,
                            $status
                        );
                    continue;
                }

                $info = [
                    'Profile Id' => $profile->getId(),
                    'Store Id' => $store->getId(),
                    'Total Synced' => count($groupedEventArray)
                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);

                $this->eventResourceModel->updateSyncStatus(
                    array_keys($events),
                    Event::STATUS_SYNCED,
                    $this->apsisCoreHelper
                );
            } catch (Throwable $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e);
                continue;
            }
        }
    }

    /**
     * @param Event $event
     *
     * @return array
     */
    private function getEventArr(Event $event): array
    {
        $isSecure = $this->apsisCoreHelper->isStoreFrontSecure($event->getStoreId());
        $eventData = [];
        $createdAt = (string) $this->apsisDateHelper->formatDateForPlatformCompatibility($event->getCreatedAt(), 'c');
        $withAddedSecond = '';
        if ((int) $event->getType() === Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART ||
            (int) $event->getType() === Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER) {
            $typeArray = $this->eventsDiscriminatorMapping[$event->getType()];

            if (empty($this->eventsVersionMapping[$typeArray['main']]) ||
                empty($this->eventsVersionMapping[$typeArray['sub']])
            ) {
                return $eventData;
            }

            $mainData = (array) $this->apsisCoreHelper->unserialize($event->getEventData());
            $subData = (array) $this->apsisCoreHelper
                ->unserialize($this->getData($isSecure, $event->getSubEventData()));
            $eventData[] = [
                'event_time' => $createdAt,
                'version_id' => $this->eventsVersionMapping[$typeArray['main']],
                'data' => $mainData,
            ];
            foreach ($subData as $item) {
                if (empty($withAddedSecond)) {
                    $withAddedSecond = $createdAt;
                }
                $eventData[] = [
                    'event_time' => $withAddedSecond = $this->apsisDateHelper->addSecond($withAddedSecond, 'c'),
                    'version_id' => $this->eventsVersionMapping[$typeArray['sub']],
                    'data' => (array) $item,
                ];
            }
        } else {
            if (empty($this->eventsVersionMapping[$this->eventsDiscriminatorMapping[$event->getType()]])) {
                return $eventData;
            }

            $eventData[] = [
                'event_time' => $createdAt,
                'version_id' => $this->eventsVersionMapping[$this->eventsDiscriminatorMapping[$event->getType()]],
                'data' => (array) $this->apsisCoreHelper
                    ->unserialize($this->getData($isSecure, $event->getEventData())),
            ];
        }

        return $eventData;
    }

    /**
     * @param bool $isSecure
     * @param string $data
     *
     * @return string
     */
    private function getData(bool $isSecure, string $data): string
    {
        $isSecureNeeded = $isSecure && str_contains($data, 'http:');
        return $isSecureNeeded ? str_replace('http:', 'https:', $data) : $data;
    }

    /**
     * @param EventCollection $eventCollection
     *
     * @return array
     */
    private function getEventsArrayGroupedByProfile(EventCollection $eventCollection): array
    {
        $profileCollection = $this->profileCollectionFactory->create()
            ->getCollectionFromIds($eventCollection->getColumnValues('profile_id'));
        $groupedEvents = [];
        /** @var Profile $profile */
        foreach ($profileCollection as $profile) {
            $groupedEvents[$profile->getId()]['profile'] = $profile;
        }
        foreach ($eventCollection as $event) {
            if (! isset($groupedEvents[$event->getProfileId()]['eventCount'])) {
                $groupedEvents[$event->getProfileId()]['eventCount'] = 0;
            }
            if ($groupedEvents[$event->getProfileId()]['eventCount'] <= self::PROFILE_EVENT_LIMIT) {
                $groupedEvents[$event->getProfileId()]['events'][$event->getId()] = $event;
                $groupedEvents[$event->getProfileId()]['eventCount']++;
            }
        }
        return $groupedEvents;
    }

    /**
     * @param Client $client
     * @param Profile $profile
     *
     * @return bool|stdClass|string
     */
    private function syncProfileForEvent(Client $client, Profile $profile)
    {
        $attributesToSync[$this->attributeVerIds[ApsisCoreHelper::EMAIL_DISCRIMINATOR]] = $profile->getEmail();
        return $client->addAttributesToProfile(
            $this->keySpace,
            $profile->getId(),
            $this->section,
            $attributesToSync
        );
    }
}
