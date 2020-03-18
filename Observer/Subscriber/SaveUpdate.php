<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Event;
use Apsis\One\Model\EventFactory;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ProfileFactory;
use Apsis\One\Model\Profile;
use \Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class SaveUpdate implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var ProfileFactory
     */
    private $profileFactory;

    /**
     * SaveUpdate constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventFactory $eventFactory
     * @param EventResource $eventResource
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param ProfileFactory $profileFactory
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventFactory $eventFactory,
        EventResource $eventResource,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        ProfileFactory $profileFactory
    ) {
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->profileFactory = $profileFactory;
        $this->eventFactory = $eventFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventResource = $eventResource;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /** @var Subscriber $subscriber */
        $subscriber = $observer->getEvent()->getSubscriber();
        $store = $this->apsisCoreHelper->getStore($subscriber->getStoreId());
        $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());

        if ($account) {
            $profile = $this->profileCollectionFactory->create()
                ->loadByEmailAndStoreId(
                    $subscriber->getEmail(),
                    $subscriber->getStoreId()
                );

            if (! $profile) {
                $this->createProfile($subscriber);
            } else {
                $this->updateProfile($subscriber, $profile, $store);
            }
        }

        return $this;
    }

    /**
     * @param Subscriber $subscriber
     * @param Profile $profile
     * @param StoreInterface $store
     */
    private function updateProfile(Subscriber $subscriber, Profile $profile, StoreInterface $store)
    {
        try {
            if ($profile->getIsSubscriber() && $subscriber->getStatus() == Subscriber::STATUS_UNSUBSCRIBED) {
                $this->registerSubscriberUnsubscribeEvent($subscriber, $profile, $store);
                $profile->setSubscriberStatus(Subscriber::STATUS_UNSUBSCRIBED)
                    ->setSubscriberSyncStatus(Profile::SYNC_STATUS_PENDING)
                    ->setErrorMessage('');
                $this->profileResource->save($profile);
            } elseif ($subscriber->getStatus() == Subscriber::STATUS_SUBSCRIBED) {
                if ($profile->getIsCustomer()) {
                    $this->registerCustomerBecomesSubscriberEvent($subscriber, $profile, $store);
                }
                $profile->setSubscriberId($subscriber->getSubscriberId())
                    ->setSubscriberStatus(Subscriber::STATUS_SUBSCRIBED)
                    ->setIsSubscriber(Profile::IS_FLAGGED)
                    ->setSubscriberSyncStatus(Profile::SYNC_STATUS_PENDING)
                    ->setErrorMessage('');
                $this->profileResource->save($profile);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
        }
    }

    /**
     * @param Subscriber $subscriber
     * @param Profile $profile
     * @param StoreInterface $store
     */
    private function registerCustomerBecomesSubscriberEvent(
        Subscriber $subscriber,
        Profile $profile,
        StoreInterface $store
    ) {
        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_2_SUBSCRIBER
        );
        if ($event && $profile->getIsCustomer() && ! $profile->getIsSubscriber()) {
            $eventModel = $this->eventFactory->create()
                ->setEventType(Event::EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER)
                ->setEventData($this->apsisCoreHelper->serialize(
                    $this->getDataArrForSubscriberEvent($subscriber, $profile)
                ))
                ->setProfileId($profile->getId())
                ->setSubscriberId($subscriber->getSubscriberId())
                ->setCustomerId($profile->getCustomerId())
                ->setStoreId($subscriber->getStoreId())
                ->setEmail($subscriber->getEmail())
                ->setStatus(Profile::SYNC_STATUS_PENDING);
            try {
                $this->eventResource->save($eventModel);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            }
        }
    }

    /**
     * @param Subscriber $subscriber
     * @param Profile $profile
     * @param StoreInterface $store
     */
    private function registerSubscriberUnsubscribeEvent(Subscriber $subscriber, Profile $profile, StoreInterface $store)
    {
        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_SUBSCRIBER_UNSUBSCRIBE
        );
        if ($event) {
            $eventModel = $this->eventFactory->create()
                ->setEventType(Event::EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE)
                ->setEventData($this->apsisCoreHelper->serialize(
                    $this->getDataArrForUnsubscribeEvent($subscriber)
                ))
                ->setProfileId($profile->getId())
                ->setSubscriberId($subscriber->getSubscriberId())
                ->setStoreId($subscriber->getStoreId())
                ->setEmail($subscriber->getEmail())
                ->setStatus(Profile::SYNC_STATUS_PENDING);
            try {
                $this->eventResource->save($eventModel);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            }
        }
    }

    /**
     * @param Subscriber $subscriber
     *
     * @return array
     */
    private function getDataArrForUnsubscribeEvent(Subscriber $subscriber)
    {
        $data = [
            'subscriber_id' => (int) $subscriber->getSubscriberId(),
            'unsubscribe_at' => (string) $this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($subscriber->getChangeStatusAt()),
            'website_name' => (string) $this->apsisCoreHelper
                ->getWebsiteNameFromStoreId($subscriber->getStoreId()),
            'store_name' => (string) $this->apsisCoreHelper->getStoreNameFromId($subscriber->getStoreId())
        ];
        return $data;
    }

    /**
     * @param Subscriber $subscriber
     * @param Profile $profile
     *
     * @return array
     */
    private function getDataArrForSubscriberEvent(Subscriber $subscriber, Profile $profile)
    {
        $data = [
            'subscriber_id' => (int) $subscriber->getSubscriberId(),
            'customer_id' => (int) $profile->getCustomerId(),
            'register_at' => (string) $this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($subscriber->getChangeStatusAt()),
            'website_name' => (string) $this->apsisCoreHelper
                ->getWebsiteNameFromStoreId($subscriber->getStoreId()),
            'store_name' => (string) $this->apsisCoreHelper->getStoreNameFromId($subscriber->getStoreId())
        ];
        return $data;
    }

    /**
     * @param Subscriber $subscriber
     */
    private function createProfile(Subscriber $subscriber)
    {
        if ($subscriber->getStatus() == Subscriber::STATUS_SUBSCRIBED) {
            try {
                $profile = $this->profileFactory->create()
                    ->setSubscriberId($subscriber->getSubscriberId())
                    ->setSubscriberStatus(Subscriber::STATUS_SUBSCRIBED)
                    ->setStoreId($subscriber->getStoreId())
                    ->setEmail($subscriber->getEmail())
                    ->setIsSubscriber(Profile::IS_FLAGGED);
                $this->profileResource->save($profile);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            }
        }
    }
}
