<?php

namespace Apsis\One\Model\Service;

use Apsis\One\ApiClient\Client;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Stdlib\Cookie\PhpCookieManagerFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Apsis\One\Model\Profile as ProfileModel;
use Exception;
use Magento\Newsletter\Model\Subscriber;
use Apsis\One\Model\ProfileFactory;
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
     * Profile constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param PhpCookieManagerFactory $phpCookieManager
     * @param PublicCookieMetadataFactory $cookieMetadataFactory
     * @param ProfileResource $profileResource
     * @param ProfileFactory $profileFactory
     * @param Event $eventService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        PhpCookieManagerFactory $phpCookieManager,
        PublicCookieMetadataFactory $cookieMetadataFactory,
        ProfileResource $profileResource,
        ProfileFactory $profileFactory,
        Event $eventService
    ) {
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
                    ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
                    ->setIsSubscriber(ProfileModel::NO_FLAGGED)
                    ->setErrorMessage('');
                $this->profileResource->save($profile);
            } elseif ((int) $subscriber->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED) {
                if ($profile->getIsCustomer()) {
                    $this->eventService->registerCustomerBecomesSubscriberEvent($subscriber, $profile, $store);
                }
                $profile->setSubscriberId($subscriber->getSubscriberId())
                    ->setSubscriberStatus(Subscriber::STATUS_SUBSCRIBED)
                    ->setIsSubscriber(ProfileModel::IS_FLAGGED)
                    ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
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
                $this->eventService->updateEmailInEventsForCustomer($profile, $customer);
                $profile->setEmail($customer->getEmail());
            }
            $profile->setCustomerSyncStatus(ProfileModel::SYNC_STATUS_PENDING)
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
            (int) $customer->getWebsiteId(),
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
                (int) $this->apsisCoreHelper->getStore($subscriber->getStoreId())->getWebsiteId(),
                (string) $subscriber->getEmail(),
                (int) $subscriber->getSubscriberId()
            );
        }
    }

    /**
     * @param int $storeId
     * @param int $websiteId
     * @param string $email
     * @param int $subscriberId
     * @param int $customerId
     */
    private function createProfile(
        int $storeId,
        int $websiteId,
        string $email,
        int $subscriberId,
        int $customerId = 0
    ) {
        try {
            $profile = $this->profileFactory->create();
            $profile->setStoreId($storeId)
                ->setWebsiteId($websiteId)
                ->setEmail($email);
            if ($customerId) {
                $profile->setCustomerId($customerId)
                    ->setIsCustomer(ProfileModel::IS_FLAGGED);
            }
            if ($subscriberId) {
                $profile->setSubscriberId($subscriberId)
                    ->setSubscriberStatus(Subscriber::STATUS_SUBSCRIBED)
                    ->setIsSubscriber(ProfileModel::IS_FLAGGED);
            }
            $this->profileResource->save($profile);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }
}
