<?php

namespace Apsis\One\Model\Service;

use Apsis\One\ApiClient\Client;
use Apsis\One\Model\Profile as ProfileModel;
use Apsis\One\Model\ProfileFactory;
use Apsis\One\Model\Queue;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Queue as ApsisQueueService;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Stdlib\Cookie\PhpCookieManagerFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Api\Data\StoreInterface;
use Throwable;
use Zend_Date;

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
     * @var ApsisQueueService
     */
    private ApsisQueueService $apsisQueueService;

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
     * @param ApsisQueueService $apsisQueueService
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
        ApsisQueueService $apsisQueueService
    ) {
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->subscriberFactory = $subscriberFactory;
        $this->eventService = $eventService;
        $this->profileFactory = $profileFactory;
        $this->profileResource = $profileResource;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->phpCookieManager = $phpCookieManager;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->apsisQueueService = $apsisQueueService;
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
        $sectionDiscriminator = $this->apsisCoreHelper
            ->getStoreConfig($store, ApsisCoreHelper::PATH_APSIS_CONFIG_SECTION);
        $integrationKeySpace = $this->apsisCoreHelper
            ->getStoreConfig($store, ApsisCoreHelper::PATH_APSIS_CONFIG_PROFILE_KEY);
        if (empty($sectionDiscriminator) || empty($integrationKeySpace)) {
            return;
        }

        $apiClient = $this->apsisCoreHelper->getApiClient($store);
        $keySpacesToMerge = $this->getKeySpacesToMerge($profile, $integrationKeySpace);
        if ($apiClient && ! empty($keySpacesToMerge)) {
            if ($this->syncProfile($apiClient, $sectionDiscriminator, $profile, $customer, $integrationKeySpace)) {
                //If conflict on merge then set new cookie value for web keyspace
                if ($apiClient->mergeProfile($keySpacesToMerge) === Client::HTTP_CODE_CONFLICT) {
                    //Log it
                    $this->apsisCoreHelper->debug(__METHOD__, ['Message' => 'Conflict, creating new cookie.']);

                    //Create new cookie value
                    $keySpacesToMerge[1]['profile_key'] =
                        md5($profile->getId() . date(Zend_Date::TIMESTAMP));

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
     * @param string $integrationKeySpace
     *
     * @return array
     */
    private function getKeySpacesToMerge(ProfileModel $profile, string $integrationKeySpace): array
    {
        $keySpacesToMerge = [];

        try {
            $elyCookieValue = $this->phpCookieManager->create()->getCookie(self::APSIS_WEB_COOKIE_NAME);
            if (! empty($elyCookieValue) && ! empty($integrationKeySpace)) {
                $keySpacesToMerge = [
                    [
                        'keyspace_discriminator' => $integrationKeySpace,
                        'profile_key' => $profile->getId()
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
     * @param ProfileModel $profile
     * @param CustomerInterface $customer
     * @param string $integrationKeySpace
     *
     * @return bool
     */
    private function syncProfile(
        Client $apiClient,
        string $sectionDiscriminator,
        ProfileModel $profile,
        CustomerInterface $customer,
        string $integrationKeySpace
    ): bool {
        try {
            //If attribute version id array is empty, return false
            if (empty($attrArrWithVersionIds =
                $this->apsisCoreHelper->getAttributeVersionIds($apiClient, $sectionDiscriminator))
            ) {
                return false;
            }

            //Minimum, Email is needed
            if (! empty($attrArrWithVersionIds[ApsisCoreHelper::EMAIL_DISCRIMINATOR])) {
                $attributesToSync[$attrArrWithVersionIds[ApsisCoreHelper::EMAIL_DISCRIMINATOR]] = $customer->getEmail();
                return ($apiClient->addAttributesToProfile(
                    $integrationKeySpace,
                    $profile->getId(),
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
     *
     * @return void
     */
    public function updateProfileForSubscriber(Subscriber $subscriber, ProfileModel $profile): void
    {
        try {
            $profile->setSubscriberId($subscriber->getSubscriberId())
                ->setStoreId($subscriber->getStoreId())
                ->setIsSubscriber(1)
                ->setSubscriberStatus($subscriber->getSubscriberStatus())
                ->setErrorMessage('');
            $this->profileResource->save($profile);

            if ((int) $subscriber->getSubscriberStatus() === Subscriber::STATUS_UNSUBSCRIBED) {
                $this->eventService->registerSubscriberUnsubscribeEvent($subscriber, $profile);
                $subscription = Queue::CONSENT_OPT_OUT;
            } elseif ((int) $subscriber->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED) {
                $this->eventService->registerCustomerBecomesSubscriberEvent($subscriber, $profile);
                $subscription = Queue::CONSENT_OPT_IN;
            }

            if (isset($subscription)) {
                $this->apsisQueueService->registerItem($profile, $subscription, $this->apsisCoreHelper);
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
            if ($profile->getIsSubscriber()) {
                $this->eventService->registerSubscriberBecomesCustomerEvent($customer, $profile);
            }

            if ($customer->getEmail() != $profile->getEmail()) {
                $this->eventService->updateEmailInEventsForCustomer($profile, $customer);
                $profile->setEmail($customer->getEmail());
            }

            $profile->setStoreId($customer->getStoreId())
                ->setEmail($customer->getEmail())
                ->setCustomerId($customer->getEntityId())
                ->setGroupId($customer->getGroupId())
                ->setIsCustomer(1)
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
            null,
            (int) $customer->getId(),
            (int) $customer->getGroupId()
        );
    }

    /**
     * @param Subscriber $subscriber
     *
     * @return void
     */
    public function createProfileForSubscriber(Subscriber $subscriber): void
    {
        $this->createProfile(
            (int) $subscriber->getStoreId(),
            (string) $subscriber->getEmail(),
            (int) $subscriber->getSubscriberId(),
            null,
            null,
            $subscriber->getSubscriberStatus()
        );
    }

    /**
     * @param int $storeId
     * @param string $email
     * @param int|null $subscriberId
     * @param int|null $customerId
     * @param int|null $groupId
     * @param int|null $subscriberStatus
     *
     * @return void
     */
    private function createProfile(
        int $storeId,
        string $email,
        int $subscriberId = null,
        int $customerId = null,
        int $groupId = null,
        int $subscriberStatus = null
    ): void {
        try {
            $profile = $this->profileFactory->create();
            $profile->setEmail($email)
                ->setStoreId($storeId);

            if ($customerId) {
                $profile->setCustomerId($customerId)
                    ->setGroupId($groupId)
                    ->setIsCustomer(1);
            }

            if ($subscriberId) {
                $profile->setSubscriberId($subscriberId)
                    ->setSubscriberStatus($subscriberStatus)
                    ->setIsSubscriber(1);
            }

            $this->profileResource->save($profile);

            if ($subscriberStatus === Subscriber::STATUS_SUBSCRIBED) {
                $subscription = Queue::CONSENT_OPT_IN;
            } elseif ($subscriberStatus === Subscriber::STATUS_UNSUBSCRIBED) {
                $subscription = Queue::CONSENT_OPT_OUT;
            }

            if (isset($subscription)) {
                $this->apsisQueueService->registerItem($profile, $subscription, $this->apsisCoreHelper);
            }
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
     * @param string $deleteType
     *
     * @return void
     */
    public function handleProfileDeleteOperation(ProfileModel $profile, string $deleteType): void
    {
        try {
            if (in_array($deleteType, ['customer', 'subscriber'])) {
                if ($deleteType === 'customer') {
                    if ($profile->getIsSubscriber()) {
                        $profile->setCustomerId(null)
                            ->setGroupId(null)
                            ->setIsCustomer(0);
                        $this->profileResource->save($profile);
                    } else {
                        $proceedDelete = true;
                    }
                }

                if ($deleteType === 'subscriber') {
                    if ($profile->getIsCustomer()) {
                        $profile->setSubscriberId(null)
                            ->setIsSubscriber(0)
                            ->setSubscriberStatus(null);
                        $this->profileResource->save($profile);

                        // Register consent update
                        $this->apsisQueueService
                            ->registerItem($profile, Queue::CONSENT_OPT_OUT, $this->apsisCoreHelper);
                    } else {
                        $proceedDelete = true;
                    }
                }

                if (! empty($proceedDelete)) {
                    $this->profileResource->delete($profile);
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
