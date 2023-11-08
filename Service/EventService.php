<?php

namespace Apsis\One\Service;

use Apsis\One\Logger\Logger;
use Apsis\One\Service\Api\ClientApi;
use Apsis\One\Model\EventModel;
use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\ResourceModel\EventResource;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Apsis\One\Model\ResourceModel\Event\EventCollection;
use Apsis\One\Model\ResourceModel\Event\EventCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile\ProfileCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile\ProfileCollection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronCollectionFactory;
use Throwable;

class EventService extends AbstractCronService
{
    const REGISTRY_NAME_SUBSCRIBER_UPDATE = '_subscriber_save_after';

    /**
     * Maximum collection limit per store
     */
    const COLLECTION_LIMIT = 500;
    /**
     * Maximum event limit per profile
     */
    const PROFILE_EVENT_LIMIT = 25;

    /**
     * Event discriminators
     */
    const SUBSCRIPTION_CHANGED_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.subscription-changed';
    const LOGGED_IN_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.logged-in';
    const PRODUCT_WISHED__DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.product-wished';
    const PRODUCT_REVIEWED_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.product-reviewed';
    const CART_ABANDONED_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.cart-abandoned-cart';
    const CART_ABANDONED_PRODUCT_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.cart-abandoned-product';
    const ORDER_PLACED_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.order-placed-order';
    const ORDER_PLACED_PRODUCT_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.order-placed-product';
    const PRODUCT_CARTED_DISCRIMINATOR = 'com.apsis1.integrations.adobe-commerce.events.product-carted';

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var EventCollectionFactory
     */
    private EventCollectionFactory $eventCollectionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var ApiService
     */
    private ApiService $apiService;

    /**
     * @var array
     */
    private array $eventsDiscriminatorMapping = [
        EventModel::EVENT_SUBSCRIPTION_CHANGED => self::SUBSCRIPTION_CHANGED_DISCRIMINATOR,
        EventModel::EVENT_LOGGED_IN => self::LOGGED_IN_DISCRIMINATOR,
        EventModel::EVENT_PRODUCT_WISHED => self::PRODUCT_WISHED__DISCRIMINATOR,
        EventModel::EVENT_PRODUCT_REVIEWED => self::PRODUCT_REVIEWED_DISCRIMINATOR,
        EventModel::EVENT_CART_ABANDONED => [
            'main' => self::CART_ABANDONED_DISCRIMINATOR,
            'sub' => self::CART_ABANDONED_PRODUCT_DISCRIMINATOR
        ],
        EventModel::EVENT_PLACED_ORDER => [
            'main' => self::ORDER_PLACED_DISCRIMINATOR,
            'sub' => self::ORDER_PLACED_PRODUCT_DISCRIMINATOR
        ],
        EventModel::EVENT_PRODUCT_CARTED => self::PRODUCT_CARTED_DISCRIMINATOR,
    ];

    /**
     * @var array
     */
    private array $eventsVersionMapping = [
        self::SUBSCRIPTION_CHANGED_DISCRIMINATOR => false,
        self::LOGGED_IN_DISCRIMINATOR => false,
        self::PRODUCT_WISHED__DISCRIMINATOR => false,
        self::PRODUCT_REVIEWED_DISCRIMINATOR => false,
        self::CART_ABANDONED_DISCRIMINATOR => false,
        self::CART_ABANDONED_PRODUCT_DISCRIMINATOR => false,
        self::ORDER_PLACED_DISCRIMINATOR => false,
        self::ORDER_PLACED_PRODUCT_DISCRIMINATOR => false,
        self::PRODUCT_CARTED_DISCRIMINATOR => false
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
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param CronCollectionFactory $cronCollectionFactory
     * @param ModuleListInterface $moduleList
     * @param EventResource $eventResource
     * @param EventCollectionFactory $eventCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ApiService $apiService
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        CronCollectionFactory $cronCollectionFactory,
        ModuleListInterface $moduleList,
        EventResource $eventResource,
        EventCollectionFactory $eventCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        ApiService $apiService
    ) {
        parent::__construct($logger, $storeManager, $writer, $cronCollectionFactory, $moduleList);
        $this->apiService = $apiService;
        $this->eventResource = $eventResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->eventCollectionFactory = $eventCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    protected function getEntityCronJobCode(): string
    {
        return 'apsis_one_sync_events';
    }

    /**
     * @inheritDoc
     */
    protected function runEntityCronjobTaskByStore(StoreInterface $store): void
    {
        try {
            if (! $this->getStoreConfig($store, BaseService::PATH_CONFIG_EVENT_ENABLED)) {
                return;
            }

            $configModel = $this->apiService->configService->getActiveConfigForStore($store->getId());
            if (empty($configModel) || empty($configModel->getApiConfig())) {
                return;
            }

            $this->section = $configModel->getApiConfig()->getSectionDiscriminator();
            $this->keySpace = $configModel->getApiConfig()->getKeyspaceDiscriminator();
            $client = $this->apiService->getApiClient($store, $configModel);

            // Validate all things compulsory
            if (! $this->section || ! $this->keySpace || ! $client) {
                return;
            }

            $eventCollection = $this->getEventCollection()
                ->getPendingEventsByStore($store->getId(), self::COLLECTION_LIMIT);
            if (! $eventCollection->getSize()) {
                return;
            }

            // Validate we have all necessary attribute version ids
            $this->attributeVerIds = $this->apiService->getAttributeVersionIds($client, $this->section);
            if (empty($this->attributeVerIds) ||
                ! isset($this->attributeVerIds[BaseService::EMAIL_DISCRIMINATOR])
            ) {
                return;
            }

            // Validate we have all necessary event version ids
            $this->eventsVersionMapping = $this->apiService
                ->getAllEventVersionIds($client, $this->section, $this->eventsVersionMapping);
            if (empty($this->eventsVersionMapping) || ! $this->isMinimumEventsMapped()) {
                return;
            }

            //At this point proceed for actual batching of events to sync
            $this->processEventCollection($client, $eventCollection, $store);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return;
        }
    }

    /**
     * @return EventCollection
     */
    private function getEventCollection(): EventCollection
    {
        return $this->eventCollectionFactory->create();
    }

    /**
     * @return ProfileCollection
     */
    private function getProfileCollection(): ProfileCollection
    {
        return $this->profileCollectionFactory->create();
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
     * @param ClientApi $client
     * @param EventCollection $eventCollection
     * @param StoreInterface $store
     *
     * @return void
     */
    private function processEventCollection(
        ClientApi       $client,
        EventCollection $eventCollection,
        StoreInterface  $store,
    ): void {
        $groupedEvents = $this->getEventsArrayGroupedByProfile($eventCollection);
        foreach ($groupedEvents as $profileEvents) {
            try {
                /** @var ProfileModel $profile */
                $profile = $profileEvents['profile'];
                $events = $profileEvents['events'];
                if (empty($events)) {
                    continue;
                }

                $status = $this->syncProfileForEvent($client, $profile);
                if ($status === false) {
                    if (getenv('APSIS_DEVELOPER')) {
                        $this->log(
                            __METHOD__ . ': Unable to sync profile for events for Store: ' . $store->getCode() .
                            ' Profile: ' . $profile->getId()
                        );
                    }
                    continue;
                } elseif (is_string($status)) {
                    $msg = 'Unable to sync profile with error: ' . $status;
                    $this->eventResource
                        ->updateItemsByIds(
                            array_keys($events),
                            ['sync_status' => EventModel::STATUS_FAILED, 'error_message' => $msg],
                            $this
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
                    if (getenv('APSIS_DEVELOPER')) {
                        $this->log(
                            __METHOD__ . ': Unable to post events for store id ' . $store->getId() .
                            ' profile ' . $profile->getId()
                        );
                    }
                    continue;
                } elseif (is_string($status)) {
                    $this->eventResource
                        ->updateItemsByIds(
                            array_keys($events),
                            ['sync_status' => EventModel::STATUS_FAILED, 'error_message' => $status],
                            $this
                        );
                    continue;
                }

                if (getenv('APSIS_DEVELOPER')) {
                    $info = [
                        'Profile Id' => $profile->getId(),
                        'Store Id' => $store->getId(),
                        'Total Synced' => count($groupedEventArray)
                    ];
                    $this->debug(__METHOD__, $info);
                }

                $this->eventResource
                    ->updateItemsByIds(
                        array_keys($events),
                        ['sync_status' => EventModel::STATUS_SYNCED],
                        $this
                    );
            } catch (Throwable $e) {
                $this->logError(__METHOD__, $e);
                continue;
            }
        }
    }

    /**
     * @param EventModel $event
     *
     * @return array
     */
    private function getEventArr(EventModel $event): array
    {
        try {
            $isSecure = $this->isStoreFrontSecure($event->getStoreId());
            $eventData = [];
            $createdAt = (string) $this->formatDateForPlatformCompatibility($event->getCreatedAt(), 'c');
            $withAddedSecond = '';
            if ((int) $event->getType() === EventModel::EVENT_CART_ABANDONED ||
                (int) $event->getType() === EventModel::EVENT_PLACED_ORDER) {
                $typeArray = $this->eventsDiscriminatorMapping[$event->getType()];

                if (empty($this->eventsVersionMapping[$typeArray['main']]) ||
                    empty($this->eventsVersionMapping[$typeArray['sub']])
                ) {
                    return $eventData;
                }

                $mainData = json_decode($event->getEventData(), true);
                $subData = json_decode($this->getData($isSecure, $event->getSubEventData()), true);
                $eventData[] = [
                    'event_time' => $createdAt,
                    'version_id' => $this->eventsVersionMapping[$typeArray['main']],
                    'data' => $mainData
                ];
                foreach ($subData as $item) {
                    if (empty($withAddedSecond)) {
                        $withAddedSecond = $createdAt;
                    }
                    $eventData[] = [
                        'event_time' => $withAddedSecond = $this->addSecond($withAddedSecond, 'c'),
                        'version_id' => $this->eventsVersionMapping[$typeArray['sub']],
                        'data' => (array) $item
                    ];
                }
            } else {
                if (empty($this->eventsVersionMapping[$this->eventsDiscriminatorMapping[$event->getType()]])) {
                    return $eventData;
                }

                $eventData[] = [
                    'event_time' => $createdAt,
                    'version_id' => $this->eventsVersionMapping[$this->eventsDiscriminatorMapping[$event->getType()]],
                    'data' => json_decode($this->getData($isSecure, $event->getEventData()), true)
                ];
            }

            return $eventData;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
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
        return $isSecureNeeded ? (string) str_replace('http:', 'https:', $data) : $data;
    }

    /**
     * @param EventCollection $eventCollection
     *
     * @return array
     */
    private function getEventsArrayGroupedByProfile(EventCollection $eventCollection): array
    {
        try {
            $profileCollection = $this->getProfileCollection()
                ->getCollection('id', $eventCollection->getColumnValues('profile_id'));
            $groupedEvents = [];
            /** @var ProfileModel $profile */
            foreach ($profileCollection as $profile) {
                $groupedEvents[$profile->getId()]['profile'] = $profile;
            }
            /** @var EventModel $event */
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
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param ClientApi $client
     * @param ProfileModel $profile
     *
     * @return mixed
     */
    private function syncProfileForEvent(ClientApi $client, ProfileModel $profile): mixed
    {
        try {
            $attributesToSync = [$this->attributeVerIds[BaseService::EMAIL_DISCRIMINATOR] => $profile->getEmail()];
            return $client->addAttributesToProfile(
                $this->keySpace,
                $profile->getId(),
                $this->section,
                $attributesToSync
            );
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }
}
