<?php

namespace Apsis\One\Model\Sync;

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
    private $subscriberEvents = [
        Event::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE,
        Event::EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER
    ];

    /**
     * @var array
     */
    private $customerEvents = [
        Event::EVENT_TYPE_CUSTOMER_LOGIN,
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST,
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
        Event::EVENT_TYPE_CUSTOMER_ABANDONED_CART,
        Event::EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER,
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER
    ];

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
            if ($account) {
                $eventCollection = $this->eventCollectionFactory->create()
                    ->getPendingEventsByStore($store->getId(), self::LIMIT);

                if ($eventCollection->getSize()) {
                    $this->processEventCollection($eventCollection, $store);
                }
            }
        }
    }

    /**
     * @param EventCollection $eventCollection
     * @param StoreInterface $store
     */
    private function processEventCollection(EventCollection $eventCollection, StoreInterface $store)
    {
        $groupedEvents = $this->getEventsArrayGroupedByProfile($eventCollection);
        foreach ($groupedEvents as $profileEvents) {
            /** @var Profile $profile */
            $profile = $profileEvents['profile'];
            $status = $this->syncProfileForEvent($profile, $store);
            if ($status === false) {
                continue;
            }

            /** @toDo create array for event api */
            $events = $profileEvents['events'];
            $this->eventResourceModel->updateSyncStatus(array_keys($events), Profile::SYNC_STATUS_SYNCED);
        }
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
     * @param Profile $profile
     * @param StoreInterface $store
     *
     * @return bool|stdClass
     */
    private function syncProfileForEvent(Profile $profile, StoreInterface $store)
    {
        $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
        /** @toDo change to Magento keyspace discriminator */
        $keySpaceDiscriminator = 'com.apsis1.keyspaces.email';
        $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        );
        /** @var @toDo update support attribute */
        $mappedEmailAttributeId = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
        );
        if ($client && $sectionDiscriminator && $mappedEmailAttributeId) {
            /** @toDo change to Magento keyspace id instead of email */
            return $client->createProfile(
                $keySpaceDiscriminator,
                $profile->getEmail(),
                $sectionDiscriminator,
                [$mappedEmailAttributeId => $profile->getEmail()]
            );
        }

        return false;
    }
}
