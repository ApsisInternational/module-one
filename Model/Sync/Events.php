<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\ApiClient\Client;
use Apsis\One\Model\Event;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResourceModel;
use Apsis\One\Model\ResourceModel\Event\Collection as EventCollection;
use Apsis\One\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResourceModel;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use stdClass;
use Zend_Date;

class Events implements SyncInterface
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
     * @var ProfileResourceModel
     */
    private $profileResourceModel;

    /**
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * @var array
     */
    private $eventsDiscriminatorMapping = [
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
    private $eventsVersionMapping = [
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
     * @var string
     */
    private $mappedProfileKeyAttribute = '';

    /**
     * @var array
     */
    private $attributesArrWithVersionId = [];

    /**
     * Profiles constructor.
     *
     * @param EventCollectionFactory $eventCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EventResourceModel $eventResourceModel
     * @param ProfileResourceModel $profileResourceModel
     * @param ApsisDateHelper $apsisDateHelper
     */
    public function __construct(
        EventCollectionFactory $eventCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        EventResourceModel $eventResourceModel,
        ProfileResourceModel $profileResourceModel,
        ApsisDateHelper $apsisDateHelper
    ) {
        $this->apsisDateHelper = $apsisDateHelper;
        $this->profileResourceModel = $profileResourceModel;
        $this->eventResourceModel = $eventResourceModel;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->eventCollectionFactory = $eventCollectionFactory;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function process(ApsisCoreHelper $apsisCoreHelper)
    {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $stores = $this->apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            try {
                $this->sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
                );
                $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
                $this->mappedEmailAttribute = $this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
                );
                $this->mappedProfileKeyAttribute = $this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_PROFILE_KEY
                );

                if ($this->sectionDiscriminator && $client && $this->mappedEmailAttribute) {
                    $this->attributesArrWithVersionId = $this->apsisCoreHelper
                        ->getAttributesArrWithVersionId($client, $this->sectionDiscriminator);
                    $this->keySpaceDiscriminator = $this->apsisCoreHelper
                        ->getKeySpaceDiscriminator($this->sectionDiscriminator);
                    $this->mapEventVersionIds($client);
                    $eventCollection = $this->eventCollectionFactory->create()
                        ->getPendingEventsByStore($store->getId(), self::COLLECTION_LIMIT);

                    if ($eventCollection->getSize() &&
                        $this->isMinimumEventsMapped() &&
                        $this->mappedEmailAttribute &&
                        isset($this->attributesArrWithVersionId[$this->mappedEmailAttribute])
                    ) {
                        $this->processEventCollection($client, $eventCollection, $store);
                    }
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
                continue;
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
        $eventDefinition = $client->getEvents($this->sectionDiscriminator);
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
     */
    private function processEventCollection(Client $client, EventCollection $eventCollection, StoreInterface $store)
    {
        $groupedEvents = $this->getEventsArrayGroupedByProfile($eventCollection);
        foreach ($groupedEvents as $profileEvents) {
            try {
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
                            Profile::SYNC_STATUS_FAILED,
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

                if (! empty($groupedEventArray)) {
                    $status = $client->addEventsToProfile(
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
                            ->updateSyncStatus(
                                array_keys($events),
                                Profile::SYNC_STATUS_FAILED,
                                $this->apsisCoreHelper,
                                $status
                            );
                        continue;
                    }

                    $this->eventResourceModel->updateSyncStatus(
                        array_keys($events),
                        Profile::SYNC_STATUS_SYNCED,
                        $this->apsisCoreHelper
                    );
                }
            } catch (Exception $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
        $createdAt = (string) $this->apsisDateHelper->formatDateForPlatformCompatibility(
            $event->getCreatedAt(),
            Zend_Date::ISO_8601
        );
        $withAddedSecond = '';
        if ((int) $event->getEventType() === Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART ||
            (int) $event->getEventType() === Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER) {
            $typeArray = $this->eventsDiscriminatorMapping[$event->getEventType()];

            if (empty($this->eventsVersionMapping[$typeArray['main']]) ||
                empty($this->eventsVersionMapping[$typeArray['sub']])
            ) {
                return $eventData;
            }

            $mainData = (array) $this->apsisCoreHelper->unserialize($event->getEventData());
            $subData = (array) $this->apsisCoreHelper->unserialize($event->getSubEventData());
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
                    'event_time' => $withAddedSecond = $this->apsisDateHelper->addSecond($withAddedSecond, Zend_Date::ISO_8601),
                    'version_id' => $this->eventsVersionMapping[$typeArray['sub']],
                    'data' => (array) $item,
                ];
            }
        } else {
            if (empty($this->eventsVersionMapping[$this->eventsDiscriminatorMapping[$event->getEventType()]])) {
                return $eventData;
            }

            $eventData[] = [
                'event_time' => $createdAt,
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
        $attributesToSync[$this->attributesArrWithVersionId[$this->mappedEmailAttribute]] = $profile->getEmail();
        if (isset($this->attributesArrWithVersionId[$this->mappedProfileKeyAttribute])) {
            $attributesToSync[$this->attributesArrWithVersionId[$this->mappedProfileKeyAttribute]]
                = $profile->getIntegrationUid();
        }
        return $client->addAttributesToProfile(
            $this->keySpaceDiscriminator,
            $profile->getIntegrationUid(),
            $this->sectionDiscriminator,
            $attributesToSync
        );
    }
}
