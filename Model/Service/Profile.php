<?php

namespace Apsis\One\Model\Service;

use Apsis\One\ApiClient\Client;
use Apsis\One\Model\Profile as ProfileModel;
use Apsis\One\Model\ProfileFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Stdlib\Cookie\PhpCookieManagerFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Zend_Date;

class Profile
{
    const WEB_KEYSPACE_DISCRIMINATOR = 'com.apsis1.keyspaces.web';
    const APSIS_WEB_COOKIE_NAME = 'Ely_vID';

    /**
     * @var PhpCookieManagerFactory
     */
    private $phpCookieManager;

    /**
     * @var PublicCookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var ProfileFactory
     */
    private $profileFactory;

    /**
     * @var Event
     */
    private $eventService;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ApsisConfigHelper
     */
    private $apsisConfigHelper;

    /**
     * Profile constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param PhpCookieManagerFactory $phpCookieManager
     * @param PublicCookieMetadataFactory $cookieMetadataFactory
     * @param ProfileResource $profileResource
     * @param ProfileFactory $profileFactory
     * @param Event $eventService
     * @param SubscriberFactory $subscriberFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ApsisConfigHelper $apsisConfigHelper
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        PhpCookieManagerFactory $phpCookieManager,
        PublicCookieMetadataFactory $cookieMetadataFactory,
        ProfileResource $profileResource,
        ProfileFactory $profileFactory,
        Event $eventService,
        SubscriberFactory $subscriberFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        ApsisConfigHelper $apsisConfigHelper
    ) {
        $this->apsisConfigHelper = $apsisConfigHelper;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->subscriberFactory = $subscriberFactory;
        $this->eventService = $eventService;
        $this->profileFactory = $profileFactory;
        $this->profileResource = $profileResource;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->phpCookieManager = $phpCookieManager;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param ProfileModel $profile
     * @param StoreInterface $store
     * @param CustomerInterface $customer
     */
    public function mergeMagentoProfileWithWebProfile(
        ProfileModel $profile,
        StoreInterface $store,
        CustomerInterface $customer
    ) {
        $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        );
        $mappedEmailAttribute = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
        );
        $apiClient = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
        if (! empty($apiClient) && ! empty($sectionDiscriminator) && ! empty($mappedEmailAttribute) &&
            ! empty($keySpacesToMerge = $this->getKeySpacesToMerge($profile, $sectionDiscriminator))
        ) {
            if ($this->isProfileSynced($apiClient, $sectionDiscriminator, $mappedEmailAttribute, $profile, $customer)) {
                if ($apiClient->mergeProfile($keySpacesToMerge) === Client::HTTP_CODE_CONFLICT) {
                    $keySpacesToMerge[1]['profile_key'] =
                        md5($profile->getIntegrationUid() . date(Zend_Date::TIMESTAMP));
                    if ($apiClient->mergeProfile($keySpacesToMerge) === null) {
                        $this->setNewCookieValue($keySpacesToMerge, $store);
                    }
                }
            }
        }
    }

    /**
     * @param ProfileModel $profile
     * @param string $sectionDiscriminator
     *
     * @return array
     */
    private function getKeySpacesToMerge(ProfileModel $profile, string $sectionDiscriminator)
    {
        $keySpacesToMerge = [];
        try {
            $magentoKeySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);
            $elyCookieValue = $this->phpCookieManager->create()->getCookie(self::APSIS_WEB_COOKIE_NAME);
            if (! empty($elyCookieValue) && ! empty($magentoKeySpaceDiscriminator)) {
                $keySpacesToMerge = [
                    [
                        'keyspace_discriminator' => $magentoKeySpaceDiscriminator,
                        'profile_key' => $profile->getIntegrationUid()
                    ],
                    [
                        'keyspace_discriminator' => self::WEB_KEYSPACE_DISCRIMINATOR,
                        'profile_key' => $elyCookieValue
                    ]
                ];
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $keySpacesToMerge;
    }

    /**
     * @param Client $apiClient
     * @param string $sectionDiscriminator
     * @param string $mappedEmailAttribute
     * @param ProfileModel $profile
     * @param CustomerInterface $customer
     *
     * @return bool
     */
    private function isProfileSynced(
        Client $apiClient,
        string $sectionDiscriminator,
        string $mappedEmailAttribute,
        ProfileModel $profile,
        CustomerInterface $customer
    ) {
        try {
            //If already synced, return true
            if ((int) $profile->getCustomerSyncStatus() === ProfileModel::SYNC_STATUS_SYNCED ||
                (int) $profile->getSubscriberSyncStatus() === ProfileModel::SYNC_STATUS_SYNCED
            ) {
                return true;
            }

            //If attribute version id array is empty, return false
            if (empty($attributesArrWithVersionId =
                $this->apsisCoreHelper->getAttributesArrWithVersionId($apiClient, $sectionDiscriminator))
            ) {
                return false;
            }

            //Minimum, Email is needed
            if (! empty($attributesArrWithVersionId[$mappedEmailAttribute])) {
                //Add email
                $attributesToSync[$attributesArrWithVersionId[$mappedEmailAttribute]] = $customer->getEmail();

                //Add first name
                if (! empty($mappedFNameAttribute = $this->apsisCoreHelper->getConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_FIRST_NAME,
                    ScopeInterface::SCOPE_STORES,
                    $profile->getStoreId()
                )) && ! empty($attributesArrWithVersionId[$mappedFNameAttribute])
                ) {
                    $attributesToSync[$attributesArrWithVersionId[$mappedFNameAttribute]] = $customer->getFirstname();
                }

                //Add last name
                if (! empty($mappedLNameAttribute = $this->apsisCoreHelper->getConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_NAME,
                    ScopeInterface::SCOPE_STORES,
                    $profile->getStoreId()
                )) && ! empty($attributesArrWithVersionId[$mappedLNameAttribute])
                ) {
                    $attributesToSync[$attributesArrWithVersionId[$mappedLNameAttribute]] = $customer->getLastname();
                }

                return ($apiClient->addAttributesToProfile(
                    $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator),
                    $profile->getIntegrationUid(),
                    $sectionDiscriminator,
                    $attributesToSync
                ) === null);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return false;
    }

    /**
     * @param array $keySpacesToMerge
     * @param StoreInterface $store
     */
    private function setNewCookieValue(array $keySpacesToMerge, StoreInterface $store)
    {
        try {
            $domain = $this->getDomainFromBaseUrl($store);
            if (strlen($domain)) {
                $cookieMetaData = $this->cookieMetadataFactory->create()
                    ->setDomain($domain)
                    ->setPath('/')
                    ->setHttpOnly(false)
                    ->setSecure(false)
                    ->setDurationOneYear();
                $this->phpCookieManager->create()
                    ->setPublicCookie(
                        self::APSIS_WEB_COOKIE_NAME,
                        $keySpacesToMerge[1]['profile_key'],
                        $cookieMetaData
                    );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param StoreInterface $store
     *
     * @return string
     */
    private function getDomainFromBaseUrl(StoreInterface $store)
    {
        $domain = '';
        try {
            $host = parse_url($store->getBaseUrl(), PHP_URL_HOST);
            if (! empty($host) && ! empty($hostArr = explode('.', $host))) {
                if (count($hostArr) > 3) {
                    $domain = sprintf('.%s', $host);
                } else {
                    $TLD = array_pop($hostArr);
                    $SLD = array_pop($hostArr);
                    $domain = sprintf('.%s.%s', $SLD, $TLD);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $domain;
    }

    /**
     * @param Subscriber $subscriber
     * @param ProfileModel $profile
     * @param StoreInterface $store
     */
    public function updateProfileForSubscriber(Subscriber $subscriber, ProfileModel $profile, StoreInterface $store)
    {
        try {
            if ($profile->getIsSubscriber() && (int) $subscriber->getStatus() === Subscriber::STATUS_UNSUBSCRIBED) {
                $this->eventService->registerSubscriberUnsubscribeEvent($subscriber, $profile, $store);
                $profile->setSubscriberStatus(Subscriber::STATUS_UNSUBSCRIBED)
                    ->setSubscriberStoreId($subscriber->getStoreId())
                    ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
                    ->setIsSubscriber(ProfileModel::NO_FLAGGED)
                    ->setErrorMessage('');
                $this->profileResource->save($profile);
            } elseif ((int) $subscriber->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED) {
                if ($profile->getIsCustomer()) {
                    $this->eventService->registerCustomerBecomesSubscriberEvent($subscriber, $profile, $store);
                }
                if ((int) $profile->getSubscriberSyncStatus() === ProfileModel::SYNC_STATUS_SYNCED &&
                    $profile->getIsSubscriber()
                ) {
                    $profile->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE);
                } else {
                    $profile->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_PENDING);
                }
                $profile->setSubscriberId($subscriber->getSubscriberId())
                    ->setSubscriberStoreId($subscriber->getStoreId())
                    ->setSubscriberStatus(Subscriber::STATUS_SUBSCRIBED)
                    ->setIsSubscriber(ProfileModel::IS_FLAGGED)
                    ->setErrorMessage('');
                $this->profileResource->save($profile);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param Customer $customer
     * @param ProfileModel $profile
     */
    public function updateProfileForCustomer(Customer $customer, ProfileModel $profile)
    {
        try {
            $this->eventService->registerSubscriberBecomesCustomerEvent($customer, $profile);
            if ($customer->getEmail() != $profile->getEmail()) {
                $this->removeOldSubscriptionsAndAddNewSubscriptions($profile, $customer);
                $this->eventService->updateEmailInEventsForCustomer($profile, $customer);
                $profile->setEmail($customer->getEmail());
            }
            $profile->setStoreId($customer->getStoreId())
                ->setCustomerSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
                ->setCustomerId($customer->getEntityId())
                ->setIsCustomer(ProfileModel::IS_FLAGGED)
                ->setErrorMessage('');
            $this->profileResource->save($profile);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param Customer $customer
     */
    public function createProfileForCustomer(Customer $customer)
    {
        $this->createProfile(
            (int) $customer->getStoreId(),
            (string) $customer->getEmail(),
            0,
            (int) $customer->getId()
        );
    }

    /**
     * @param Subscriber $subscriber
     */
    public function createProfileForSubscriber(Subscriber $subscriber)
    {
        if ((int) $subscriber->getStatus() === Subscriber::STATUS_SUBSCRIBED) {
            $this->createProfile(
                (int) $subscriber->getStoreId(),
                (string) $subscriber->getEmail(),
                (int) $subscriber->getSubscriberId()
            );
        }
    }

    /**
     * @param int $storeId
     * @param string $email
     * @param int $subscriberId
     * @param int $customerId
     */
    private function createProfile(
        int $storeId,
        string $email,
        int $subscriberId,
        int $customerId = 0
    ) {
        try {
            /** @var ProfileModel $profile */
            $profile = $this->profileFactory->create();
            $profile->setEmail($email);
            if ($customerId) {
                $profile->setCustomerId($customerId)
                    ->setStoreId($storeId)
                    ->setIsCustomer(ProfileModel::IS_FLAGGED);
            }
            if ($subscriberId) {
                $profile->setSubscriberId($subscriberId)
                    ->setSubscriberStoreId($storeId)
                    ->setSubscriberStatus(Subscriber::STATUS_SUBSCRIBED)
                    ->setIsSubscriber(ProfileModel::IS_FLAGGED);
            }
            $this->profileResource->save($profile);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param Customer $customer
     *
     * @return bool|ProfileModel
     */
    public function findProfileForCustomer(Customer $customer)
    {
        try {
            $found = $this->profileCollectionFactory->create()->loadByCustomerId($customer->getId());
            if ($found) {
                return $found;
            }
            $subscriber = $this->subscriberFactory->create()->loadByEmail($customer->getEmail());
            if ($subscriber->getId()) {
                $found = $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getId());
                if ($found) {
                    return $found;
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return false;
    }

    /**
     * @param ProfileModel $profile
     */
    public function handleCustomerDeleteForProfile(ProfileModel $profile)
    {
        try {
            $store = $this->apsisCoreHelper->getStore($profile->getSubscriberStoreId());
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
            if ($client && $sectionDiscriminator && $profile->getIntegrationUid()) {
                $IsCustomerSyncEnabled = (boolean) $this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED
                );
                $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);
                if ($profile->getSubscriberId()) {
                    $this->removeCustomerAttributesFromProfile(
                        $profile,
                        $IsCustomerSyncEnabled,
                        $client,
                        $sectionDiscriminator,
                        $store,
                        $keySpaceDiscriminator
                    );
                } else {
                    if ($IsCustomerSyncEnabled) {
                        $client->deleteProfile($keySpaceDiscriminator, $profile->getIntegrationUid());
                    }
                    $this->profileResource->delete($profile);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param Subscriber $subscriber
     *
     * @return bool|ProfileModel
     */
    public function findProfileForSubscriber(Subscriber $subscriber)
    {
        try {
            $found = $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getId());
            if ($found) {
                return $found;
            }
            if ($subscriber->getCustomerId()) {
                $found = $this->profileCollectionFactory->create()->loadByCustomerId($subscriber->getCustomerId());
                if ($found) {
                    return $found;
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return false;
    }

    /**
     * @param ProfileModel $profile
     */
    public function handleSubscriberDeleteForProfile(ProfileModel $profile)
    {
        try {
            $store = $this->apsisCoreHelper->getStore($profile->getSubscriberStoreId());
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
            if ($client && $sectionDiscriminator && $profile->getIntegrationUid()) {
                $IsSubscriberSyncEnabled = (boolean) $this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED
                );
                $this->removeConsentForSubscriberDelete(
                    $store,
                    $IsSubscriberSyncEnabled,
                    $profile,
                    $client,
                    $sectionDiscriminator
                );
                $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);
                if ($profile->getCustomerId()) {
                    $this->removeSubscriberAttributesFromProfile(
                        $profile,
                        $client,
                        $sectionDiscriminator,
                        $store,
                        $keySpaceDiscriminator,
                        $IsSubscriberSyncEnabled
                    );
                } else {
                    if ($IsSubscriberSyncEnabled) {
                        $client->deleteProfile($keySpaceDiscriminator, $profile->getIntegrationUid());
                    }
                    $this->profileResource->delete($profile);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ProfileModel $profile
     * @param Client $client
     * @param string $sectionDiscriminator
     * @param StoreInterface $store
     * @param string $keySpaceDiscriminator
     * @param bool $IsSubscriberSyncEnabled
     */
    private function removeSubscriberAttributesFromProfile(
        ProfileModel $profile,
        Client $client,
        string $sectionDiscriminator,
        StoreInterface $store,
        string $keySpaceDiscriminator,
        bool $IsSubscriberSyncEnabled
    ) {
        try {
            $profile->setSubscriberId(null)
                ->setSubscriberStoreId(null)
                ->setIsSubscriber(ProfileModel::NO_FLAGGED)
                ->setSubscriberStatus(null)
                ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_PENDING);
            $this->profileResource->save($profile);

            if ($IsSubscriberSyncEnabled) {
                $attributesArrWithVersionId = $this->apsisCoreHelper
                    ->getAttributesArrWithVersionId($client, $sectionDiscriminator);
                $subscriberAttributes = $this->apsisConfigHelper->getSubscriberAttributeMapping($store, false);
                foreach ($subscriberAttributes as $subscriberAttribute) {
                    if (isset($attributesArrWithVersionId[$subscriberAttribute])) {
                        $client->clearProfileAttribute(
                            $keySpaceDiscriminator,
                            $profile->getIntegrationUid(),
                            $sectionDiscriminator,
                            $attributesArrWithVersionId[$subscriberAttribute]
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ProfileModel $profile
     * @param bool $IsCustomerSyncEnabled
     * @param Client $client
     * @param string $sectionDiscriminator
     * @param StoreInterface $store
     * @param string $keySpaceDiscriminator
     */
    private function removeCustomerAttributesFromProfile(
        ProfileModel $profile,
        bool $IsCustomerSyncEnabled,
        Client $client,
        string $sectionDiscriminator,
        StoreInterface $store,
        string $keySpaceDiscriminator
    ) {
        try {
            $profile->setCustomerId(null)
                ->setStoreId(null)
                ->setIsCustomer(ProfileModel::NO_FLAGGED)
                ->setCustomerSyncStatus(ProfileModel::SYNC_STATUS_PENDING);
            $this->profileResource->save($profile);
            if ($IsCustomerSyncEnabled) {
                $attributesArrWithVersionId = $this->apsisCoreHelper
                    ->getAttributesArrWithVersionId($client, $sectionDiscriminator);
                $customerAttributes = $this->apsisConfigHelper->getCustomerAttributeMapping($store, false);
                foreach ($customerAttributes as $customerAttribute) {
                    if (isset($attributesArrWithVersionId[$customerAttribute])) {
                        $client->clearProfileAttribute(
                            $keySpaceDiscriminator,
                            $profile->getIntegrationUid(),
                            $sectionDiscriminator,
                            $attributesArrWithVersionId[$customerAttribute]
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ProfileModel $profile
     * @param Customer $customer
     */
    private function removeOldSubscriptionsAndAddNewSubscriptions(ProfileModel $profile, Customer $customer)
    {
        try {
            $store = $customer->getStore();
            $IsSubscriberSyncEnabled = (boolean)$this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED
            );
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $selectedConsentTopics = (string) $this->apsisCoreHelper->getStoreConfig(
                $customer->getStore(),
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
            );
            if ((int)$profile->getSubscriberSyncStatus() === ProfileModel::SYNC_STATUS_SYNCED &&
                (int)$profile->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED &&
                $IsSubscriberSyncEnabled && strlen($sectionDiscriminator) && strlen($selectedConsentTopics)
            ) {
                $additionalConsentTopics = (string) $this->apsisCoreHelper->getStoreConfig(
                    $customer->getStore(),
                    ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_ADDITIONAL_TOPIC
                );
                $topicMappings = explode(
                    ',',
                    $this->apsisCoreHelper->getMergedConfigTopics($selectedConsentTopics, $additionalConsentTopics)
                );
                $sortedTopicArr = $this->apsisCoreHelper->getConsentListTopicsToShowForProfile($profile, $topicMappings);
                $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());

                if (! empty($sortedTopicArr) && $client) {
                    $attributesArrWithVersionId = $this->apsisCoreHelper
                        ->getAttributesArrWithVersionId($client, $sectionDiscriminator);
                    $mappedEmailAttribute = $this->apsisCoreHelper->getStoreConfig(
                        $store,
                        ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
                    );
                    $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);
                    if (! empty($attributesArrWithVersionId) && $mappedEmailAttribute && $keySpaceDiscriminator &&
                        isset($attributesArrWithVersionId[$mappedEmailAttribute])
                    ) {
                        $client->addAttributesToProfile(
                            $keySpaceDiscriminator,
                            $profile->getIntegrationUid(),
                            $sectionDiscriminator,
                            [$attributesArrWithVersionId[$mappedEmailAttribute] => $customer->getEmail()]
                        );
                    }
                    foreach ($sortedTopicArr as $sortedTopics) {
                        if (! empty($sortedTopics['topics'])) {
                            //Make call to remove consent from old email
                            $this->createConsentForTopics(
                                $client,
                                $profile->getEmail(),
                                $sectionDiscriminator,
                                $sortedTopics['topics'],
                                Subscribers::CONSENT_TYPE_OPT_OUT
                            );
                            //Make call to add consent to old email
                            $this->createConsentForTopics(
                                $client,
                                $customer->getEmail(),
                                $sectionDiscriminator,
                                $sortedTopics['topics'],
                                Subscribers::CONSENT_TYPE_OPT_IN
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param Client $client
     * @param string $email
     * @param string $sectionDiscriminator
     * @param array $topics
     * @param string $type
     */
    private function createConsentForTopics(
        Client $client,
        string $email,
        string $sectionDiscriminator,
        array $topics,
        string $type
    ) {
        foreach ($topics as $topic) {
            try {
                if (! empty($topic['consent'])) {
                    $consent = explode('|', $topic['value']);
                    $client->createConsent(
                        ProfileModel::EMAIL_CHANNEL_DISCRIMINATOR,
                        $email,
                        $sectionDiscriminator,
                        $consent[0],
                        $consent[1],
                        $type
                    );
                }
            } catch (Exception $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                continue;
            }
        }
    }

    /**
     * @param StoreInterface $store
     * @param bool $IsSubscriberSyncEnabled
     * @param ProfileModel $profile
     * @param Client $client
     * @param string $sectionDiscriminator
     */
    private function removeConsentForSubscriberDelete(
        StoreInterface $store,
        bool $IsSubscriberSyncEnabled,
        ProfileModel $profile,
        Client $client,
        string $sectionDiscriminator
    ) {
        try {
            $selectedConsentTopics = (string)$this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
            );

            if ($IsSubscriberSyncEnabled && strlen($selectedConsentTopics)) {
                $additionalConsentTopics = (string)$this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_ADDITIONAL_TOPIC
                );
                $topicMappings = explode(
                    ',',
                    $this->apsisCoreHelper->getMergedConfigTopics($selectedConsentTopics, $additionalConsentTopics)
                );
                $sortedTopicArr = $this->apsisCoreHelper
                    ->getConsentListTopicsToShowForProfile($profile, $topicMappings);
                if (!empty($sortedTopicArr)) {
                    foreach ($sortedTopicArr as $sortedTopics) {
                        if (!empty($sortedTopics['topics'])) {
                            //Make call to remove consent from email
                            $this->createConsentForTopics(
                                $client,
                                $profile->getEmail(),
                                $sectionDiscriminator,
                                $sortedTopics['topics'],
                                Subscribers::CONSENT_TYPE_OPT_OUT
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }
}
