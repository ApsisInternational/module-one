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
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Stdlib\Cookie\PhpCookieManagerFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Profile
{
    const WEB_KEYSPACE_DISCRIMINATOR = 'com.apsis1.keyspaces.web';
    const APSIS_WEB_COOKIE_NAME = 'Ely_vID';

    /**
     * @var PhpCookieManagerFactory
     */
    private PhpCookieManagerFactory $phpCookieManager;

    /**
     * @var PublicCookieMetadataFactory
     */
    private PublicCookieMetadataFactory $cookieMetadataFactory;

    /**
     * @var ApsisCoreHelper
     */
    private Core $apsisCoreHelper;

    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * @var ProfileFactory
     */
    private ProfileFactory $profileFactory;

    /**
     * @var Event
     */
    private Event $eventService;

    /**
     * @var SubscriberFactory
     */
    private SubscriberFactory $subscriberFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var ApsisConfigHelper
     */
    private Config $apsisConfigHelper;

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
     *
     * @return void
     */
    public function mergeMagentoProfileWithWebProfile(
        ProfileModel $profile,
        StoreInterface $store,
        CustomerInterface $customer
    ): void {
        $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::MAPPINGS_SECTION_SECTION
        );
        $mappedEmailAttribute = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
        );
        if (empty($sectionDiscriminator) || empty($mappedEmailAttribute)) {
            return;
        }

        $apiClient = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
        $keySpacesToMerge = $this->getKeySpacesToMerge($profile, $sectionDiscriminator);

        if ($apiClient && ! empty($keySpacesToMerge)) {
            if ($this->isProfileSynced($apiClient, $sectionDiscriminator, $mappedEmailAttribute, $profile, $customer)) {
                //If conflict on merge then set new cookie value for web keyspace
                if ($apiClient->mergeProfile($keySpacesToMerge) === Client::HTTP_CODE_CONFLICT) {
                    //Log it
                    $this->apsisCoreHelper->debug(__METHOD__, ['Message' => 'Conflict, creating new cookie.']);

                    //Create new cookie value
                    $keySpacesToMerge[1]['profile_key'] = md5($profile->getIntegrationUid() . date('U'));

                    //Send second merge request
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
    private function getKeySpacesToMerge(ProfileModel $profile, string $sectionDiscriminator): array
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
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
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
    ): bool {
        try {
            //If already synced, return true
            if ((int) $profile->getCustomerSyncStatus() === ProfileModel::SYNC_STATUS_SYNCED ||
                (int) $profile->getSubscriberSyncStatus() === ProfileModel::SYNC_STATUS_SYNCED
            ) {
                return true;
            }

            //If attribute version id array is empty, return false
            if (empty($attributesArrWithVersionId =
                $this->apsisCoreHelper->getAttributeVersionIds($apiClient, $sectionDiscriminator))
            ) {
                return false;
            }

            //Minimum, Email is needed
            if (! empty($attributesArrWithVersionId[$mappedEmailAttribute])) {
                //Add email
                $attributesToSync[$attributesArrWithVersionId[$mappedEmailAttribute]] = $customer->getEmail();

                //Add first name
                if (! empty($mappedFNameAttribute = $this->apsisCoreHelper->getConfigValue(
                    ApsisConfigHelper::MAPPINGS_CUSTOMER_FIRST_NAME,
                    ScopeInterface::SCOPE_STORES,
                    $profile->getStoreId()
                )) && ! empty($attributesArrWithVersionId[$mappedFNameAttribute])
                ) {
                    $attributesToSync[$attributesArrWithVersionId[$mappedFNameAttribute]] = $customer->getFirstname();
                }

                //Add last name
                if (! empty($mappedLNameAttribute = $this->apsisCoreHelper->getConfigValue(
                    ApsisConfigHelper::MAPPINGS_CUSTOMER_LAST_NAME,
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
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return false;
    }

    /**
     * @param array $keySpacesToMerge
     * @param StoreInterface $store
     *
     * @return void
     */
    private function setNewCookieValue(array $keySpacesToMerge, StoreInterface $store): void
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

                $this->phpCookieManager->create()->setPublicCookie(
                    self::APSIS_WEB_COOKIE_NAME,
                    $keySpacesToMerge[1]['profile_key'],
                    $cookieMetaData
                );

                //Log it
                $info = ['Name' => self::APSIS_WEB_COOKIE_NAME, 'Value' => $keySpacesToMerge[1]['profile_key']];
                $this->apsisCoreHelper->debug(__METHOD__, $info);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     *
     * @return string
     */
    private function getDomainFromBaseUrl(StoreInterface $store): string
    {
        $domain = '';
        try {
            $host = (string) parse_url($store->getBaseUrl(), PHP_URL_HOST);
            if (! empty($host) && ! empty($hostArr = explode('.', $host))) {
                if (count($hostArr) > 3) {
                    $domain = sprintf('.%s', $host);
                } else {
                    $TLD = array_pop($hostArr);
                    $SLD = array_pop($hostArr);
                    $domain = sprintf('.%s.%s', $SLD, $TLD);
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $domain;
    }

    /**
     * @param Subscriber $subscriber
     * @param ProfileModel $profile
     * @param StoreInterface $store
     *
     * @return void
     */
    public function updateProfileForSubscriber(
        Subscriber $subscriber,
        ProfileModel $profile,
        StoreInterface $store
    ): void {
        try {
            if ($profile->getIsSubscriber() && (int) $subscriber->getStatus() === Subscriber::STATUS_UNSUBSCRIBED) {
                $this->eventService->registerSubscriberUnsubscribeEvent($subscriber, $profile, $store);

                $profile->setSubscriberStatus(Subscriber::STATUS_UNSUBSCRIBED)
                    ->setSubscriberStoreId($subscriber->getStoreId())
                    ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
                    ->setIsSubscriber(ProfileModel::NO_FLAG)
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
                    ->setIsSubscriber(ProfileModel::IS_FLAG)
                    ->setErrorMessage('');
                $this->profileResource->save($profile);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Customer $customer
     * @param ProfileModel $profile
     *
     * @return void
     */
    public function updateProfileForCustomer(Customer $customer, ProfileModel $profile): void
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
                ->setIsCustomer(ProfileModel::IS_FLAG)
                ->setErrorMessage('');
            $this->profileResource->save($profile);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Customer $customer
     *
     * @return void
     */
    public function createProfileForCustomer(Customer $customer): void
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
     *
     * @return void
     */
    public function createProfileForSubscriber(Subscriber $subscriber): void
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
     *
     * @return void
     */
    private function createProfile(
        int $storeId,
        string $email,
        int $subscriberId,
        int $customerId = 0
    ): void {
        try {
            $profile = $this->profileFactory->create();
            $profile->setEmail($email);

            if ($customerId) {
                $profile->setCustomerId($customerId)
                    ->setStoreId($storeId)
                    ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_NA)
                    ->setCustomerSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
                    ->setIsCustomer(ProfileModel::IS_FLAG);
            }

            if ($subscriberId) {
                $profile->setSubscriberId($subscriberId)
                    ->setSubscriberStoreId($storeId)
                    ->setSubscriberStatus(Subscriber::STATUS_SUBSCRIBED)
                    ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
                    ->setCustomerSyncStatus(ProfileModel::SYNC_STATUS_NA)
                    ->setIsSubscriber(ProfileModel::IS_FLAG);
            }

            $this->profileResource->save($profile);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
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
            $foundCustomerType = $this->profileCollectionFactory->create()
                ->loadByCustomerId($customer->getId());

            if ($foundCustomerType) {
                return $foundCustomerType;
            }

            $subscriber = $this->subscriberFactory->create()->loadByEmail($customer->getEmail());
            if ($subscriber->getId()) {
                $foundSubscriberType = $this->profileCollectionFactory->create()
                    ->loadBySubscriberId($subscriber->getId());

                if ($foundSubscriberType) {
                    return $foundSubscriberType;
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return false;
    }

    /**
     * @param ProfileModel $profile
     * @param string $type
     *
     * @return StoreInterface
     */
    private function getStoreForProfileType(ProfileModel $profile, string $type): StoreInterface
    {
        $storeId = 0;

        if ($type == ProfileModel::TYPE_CUSTOMER) {
            $storeId = $profile->getStoreId();
        }

        if ($type == ProfileModel::TYPE_SUBSCRIBER) {
            $storeId = $profile->getSubscriberStoreId();
        }

        return $this->apsisCoreHelper->getStore($storeId);
    }

    /**
     * @param StoreInterface $store
     * @param string $type
     *
     * @return bool
     */
    private function isSyncEnabledForProfileType(StoreInterface $store, string $type): bool
    {
        $isSyncEnabled = false;

        if ($type == ProfileModel::TYPE_CUSTOMER) {
            return (boolean) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::SYNC_SETTING_CUSTOMER_ENABLED
            );
        }

        if ($type == ProfileModel::TYPE_SUBSCRIBER) {
            return (boolean) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENABLED
            );
        }

        return $isSyncEnabled;
    }

    /**
     * @param ProfileModel $profile
     * @param string $type
     *
     * @return string
     */
    private function getActionByType(ProfileModel $profile, string $type): string
    {
        $action = '';

        if ($type == ProfileModel::TYPE_CUSTOMER) {
            $action = ($profile->getSubscriberId() && $profile->getIsSubscriber()) ? 'clearAttribute' : 'delete';
        }

        if ($type == ProfileModel::TYPE_SUBSCRIBER) {
            $action = ($profile->getCustomerId() && $profile->getIsCustomer()) ? 'clearAttribute' : 'delete';
        }

        return $action;
    }

    /**
     * @param ProfileModel $profile
     * @param string $type
     *
     * @return void
     */
    public function handleDeleteOperationByType(ProfileModel $profile, string $type): void
    {
        try {
            $store = $this->getStoreForProfileType($profile, $type);
            $isSyncEnabled = $this->isSyncEnabledForProfileType($store, $type);
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION
            );
            $action = $this->getActionByType($profile, $type);

            if ((empty($profile->getIsSubscriber()) && empty($profile->getIsSubscriber())) ||
                empty($profile->getIntegrationUid()) || $action === 'delete'
            ) {
                $this->profileResource->delete($profile);
            }

            if (empty($isSyncEnabled) || empty($sectionDiscriminator) ||
                empty($keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator))
                || empty($client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId()))
            ) {
                return;
            }

            //Remove consent from one if type is subscriber
            if ($type == ProfileModel::TYPE_SUBSCRIBER && $action !== 'delete') {
                $this->removeConsentForSubscriberDelete($store, $profile, $client, $sectionDiscriminator);
            }

            //Clear attributes from one
            if ($action === 'clearAttribute') {
                $this->removeAttributesFromProfile(
                    $profile,
                    $client,
                    $sectionDiscriminator,
                    $store,
                    $keySpaceDiscriminator,
                    $type
                );
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ProfileModel $profile
     * @param Client $client
     * @param string $section
     * @param StoreInterface $store
     * @param string $keySpace
     * @param string $type
     *
     * @param void
     */
    private function removeAttributesFromProfile(
        ProfileModel $profile,
        Client $client,
        string $section,
        StoreInterface $store,
        string $keySpace,
        string $type
    ): void {
        try {
            $attributes = [];

            //If customer delete request then clear customer attributes
            if ($type == ProfileModel::TYPE_CUSTOMER) {
                $profile->setCustomerId(null)
                    ->setStoreId(null)
                    ->setIsCustomer(ProfileModel::NO_FLAG)
                    ->setCustomerSyncStatus(ProfileModel::SYNC_STATUS_NA);

                $attributes = $this->apsisConfigHelper->getCustomerAttributeMapping($store, false);
            }

            //If subscriber delete request then clear subscriber attributes
            if ($type == ProfileModel::TYPE_SUBSCRIBER) {
                $profile->setSubscriberId(null)
                    ->setSubscriberStoreId(null)
                    ->setIsSubscriber(ProfileModel::NO_FLAG)
                    ->setSubscriberStatus(null)
                    ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_NA);

                $attributes = $this->apsisConfigHelper->getSubscriberAttributeMapping($store, false);
            }

            //Clear attributes
            $this->clearProfileAttributes($attributes, $keySpace, $section, $client, $profile);

            //Save profile
            $this->profileResource->save($profile);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array $attributes
     * @param string $keySpace
     * @param string $section
     * @param Client $client
     * @param ProfileModel $profile
     *
     * @return void
     */
    public function clearProfileAttributes(
        array $attributes,
        string $keySpace,
        string $section,
        Client $client,
        ProfileModel $profile
    ): void {
        $status = false;
        $attributesArrWithVersionId = $this->apsisCoreHelper->getAttributeVersionIds(
            $client,
            $section
        );

        if (! empty($attributesArrWithVersionId) && ! empty($attributes)) {
            foreach ($attributes as $attribute) {
                if (isset($attributesArrWithVersionId[$attribute])) {
                    $status = $client->clearProfileAttribute(
                        $keySpace,
                        $profile->getIntegrationUid(),
                        $section,
                        $attributesArrWithVersionId[$attribute]
                    );
                }
            }

            //Log it
            if ($status === null) {
                $info = [
                    'Profile Id' => $profile->getIntegrationUid(),
                    'KeySpace' => $keySpace,
                    'Section' => $section
                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);
            }
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
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return false;
    }

    /**
     * @param ProfileModel $profile
     *
     * @return void
     */
    public function deleteProfileFromOne(ProfileModel $profile): void
    {
        try {
            $storeId = $profile->getStoreId() ? $profile->getStoreId() : $profile->getSubscriberStoreId();
            $isDeleteEnabled = $this->apsisCoreHelper->getConfigValue(
                ApsisConfigHelper::PROFILE_SYNC_DELETE_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $storeId
            );

            // Only delete if enabled otherwise ignore
            if (! $isDeleteEnabled) {
                return;
            }

            $sectionDiscriminator = $this->apsisCoreHelper->getConfigValue(
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION,
                ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $storeId);

            if ($sectionDiscriminator && $keySpaceDiscriminator && $client) {
                $status = $client->deleteProfile($keySpaceDiscriminator, $profile->getIntegrationUid());

                //Log it
                if ($status === null) {
                    $info = [
                        'Request' => 'Profile removed from APSIS One.',
                        'Profile Id' => $profile->getIntegrationUid(),
                        'KeySpace' => $keySpaceDiscriminator
                    ];
                    $this->apsisCoreHelper->debug(__METHOD__, $info);
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ProfileModel $profile
     * @param Customer $customer
     *
     * @return void
     */
    private function removeOldSubscriptionsAndAddNewSubscriptions(ProfileModel $profile, Customer $customer): void
    {
        try {
            $store = $customer->getStore();
            $IsSubscriberSyncEnabled = (boolean)$this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENABLED
            );
            $sectionDiscriminator = (string) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION
            );
            $selectedConsentTopic = (string) $this->apsisCoreHelper->getStoreConfig(
                $customer->getStore(),
                ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC
            );
            if ((int)$profile->getSubscriberSyncStatus() === ProfileModel::SYNC_STATUS_SYNCED &&
                (int)$profile->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED &&
                $IsSubscriberSyncEnabled && strlen($sectionDiscriminator) && strlen($selectedConsentTopic) &&
                ! empty($topicMapping = explode('|', $selectedConsentTopic)) && isset($topicMapping[0]) &&
                $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId())
            ) {
                $attributesArrWithVersionId = $this->apsisCoreHelper
                    ->getAttributeVersionIds($client, $sectionDiscriminator);
                $mappedEmailAttribute = $this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::MAPPINGS_CUSTOMER_SUBSCRIBER_EMAIL
                );
                $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);
                if (! empty($attributesArrWithVersionId) && $mappedEmailAttribute && $keySpaceDiscriminator &&
                    isset($attributesArrWithVersionId[$mappedEmailAttribute])
                ) {
                    $status = $client->addAttributesToProfile(
                        $keySpaceDiscriminator,
                        $profile->getIntegrationUid(),
                        $sectionDiscriminator,
                        [$attributesArrWithVersionId[$mappedEmailAttribute] => $customer->getEmail()]
                    );

                    if ($status === false || is_string($status)) {
                        $this->apsisCoreHelper->log(
                            __METHOD__ . ': Unable to change email for Profile ' . $profile->getId()
                        );
                    } else {
                        $this->createConsentForTopics(
                            $client,
                            $keySpaceDiscriminator,
                            $profile->getIntegrationUid(),
                            $sectionDiscriminator,
                            $topicMapping[0],
                            Subscribers::CONSENT_TYPE_OPT_IN
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Client $client
     * @param string $keyspaceDiscriminator
     * @param string $profileKey
     * @param string $sectionDiscriminator
     * @param string $topicDiscriminator
     * @param string $type
     *
     * @return void
     */
    private function createConsentForTopics(
        Client $client,
        string $keyspaceDiscriminator,
        string $profileKey,
        string $sectionDiscriminator,
        string $topicDiscriminator,
        string $type
    ): void {
        try {
            $status = $client->createConsent(
                $keyspaceDiscriminator,
                $profileKey,
                $sectionDiscriminator,
                $topicDiscriminator,
                ProfileModel::EMAIL_CHANNEL_DISCRIMINATOR,
                $type
            );

            //Log it
            if ($status === null) {
                $info = [
                    'Profile' => $profileKey,
                    'Section' => $sectionDiscriminator,
                    'Topic' => $topicDiscriminator,
                    'Type' => $type,

                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param ProfileModel $profile
     * @param Client $client
     * @param string $sectionDiscriminator
     *
     * @return void
     */
    private function removeConsentForSubscriberDelete(
        StoreInterface $store,
        ProfileModel $profile,
        Client $client,
        string $sectionDiscriminator
    ): void {
        try {
            $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);
            $selectedConsentTopic = (string)$this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC
            );

            if (strlen($keySpaceDiscriminator) && strlen($selectedConsentTopic) &&
                ! empty($topicMapping = explode('|', $selectedConsentTopic)) && isset($topicMapping[0])
            ) {
                //Make call to remove consent from email
                $this->createConsentForTopics(
                    $client,
                    $keySpaceDiscriminator,
                    $profile->getIntegrationUid(),
                    $sectionDiscriminator,
                    $topicMapping[0],
                    Subscribers::CONSENT_TYPE_OPT_OUT
                );
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * Reset profiles and events
     *
     * @param string $from
     * @param array $extra
     *
     * @return void
     */
    public function resetRequest(string $from, array $extra = []): void
    {
        try {
            $this->apsisCoreHelper->debug(__METHOD__, ['From' => $from]);

            if (! empty($storeIds = $this->apsisCoreHelper->getStoreIdsBasedOnScope())) {
                $this->resetProfiles($from, $storeIds);
                $this->eventService->resetEvents($from, $storeIds);
            }
            $this->removeAllConfigExceptAccountConfig($from, $extra);
            $this->apsisCoreHelper->cleanCache();
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $from
     * @param array $extra
     *
     * @return void
     */
    public function removeAllConfigExceptAccountConfig(string $from, array $extra = []): void
    {
        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
        $connection = $this->profileResource->getConnection();
        $andCondPath = $connection->quoteInto(
            'AND path NOT IN(?)',
            array_merge(
                [
                    Config::ACCOUNTS_OAUTH_ENABLED,
                    Config::ACCOUNTS_OAUTH_ID,
                    Config::ACCOUNTS_OAUTH_SECRET,
                    Config::ACCOUNTS_OAUTH_REGION,
                    Config::ACCOUNTS_OAUTH_TOKEN,
                    Config::ACCOUNTS_OAUTH_TOKEN_EXPIRE,
                    Config::SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY
                ],
                $extra
            )
        );

        $andCondScope = $connection->quoteInto('AND scope = ?', $scope['context_scope']);
        $andCondScopeId = $connection->quoteInto('AND scope_id = ?', $scope['context_scope_id']);
        $status = $this->profileResource->deleteAllModuleConfig(
            $this->apsisCoreHelper,
            $andCondPath . ' ' . $andCondScope . ' ' . $andCondScopeId
        );

        if ($status) {
            $info = ['From' => $from, 'Scope' => $scope['context_scope'], 'Scope Id' => $scope['context_scope_id']];
            $this->apsisCoreHelper->debug(__METHOD__, $info);
        }
    }

    /**
     * @param string $from
     * @param array $storeIds
     * @param array $profileIds
     *
     * @return void
     */
    public function resetProfiles(string $from, array $storeIds, array $profileIds = []): void
    {
        try {
            //Reset Profiles to status Pending
            $this->profileResource->resetProfiles(
                $this->apsisCoreHelper,
                $storeIds,
                $profileIds,
                ProfileModel::SYNC_STATUS_PENDING,
                [],
                true
            );

            $info = [
                'From' => $from,
                'Store Ids' => empty($stores = implode(", ", $storeIds)) ? 'Default Scope' : $stores
            ];
            $this->apsisCoreHelper->debug(__METHOD__, $info);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
