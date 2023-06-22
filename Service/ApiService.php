<?php

namespace Apsis\One\Service;

use Apsis\One\Logger\Logger;
use Apsis\One\Service\Api\ClientApi;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Stdlib\Cookie\PhpCookieManagerFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\Api\ClientApiFactory;
use Apsis\One\Service\Sub\SubApiService;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class ApiService extends BaseService
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
     * @var ClientApiFactory
     */
    private ClientApiFactory $clientFactory;

    /**
     * @var SubApiService
     */
    private SubApiService $subApiService;

    /**
     * @var array
     */
    private array $cachedClient = [];

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param ClientApiFactory $clientFactory
     * @param SubApiService $subApiService
     * @param PhpCookieManagerFactory $phpCookieManager
     * @param PublicCookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        ClientApiFactory $clientFactory,
        SubApiService $subApiService,
        PhpCookieManagerFactory $phpCookieManager,
        PublicCookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct($logger, $storeManager, $writer);
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->phpCookieManager = $phpCookieManager;
        $this->clientFactory = $clientFactory;
        $this->subApiService = $subApiService;
    }

    /**
     * @return ClientApi
     */
    private function getClientApiModel(): ClientApi
    {
        return $this->clientFactory->create();
    }

    /**
     * @param StoreInterface $store
     *
     * @return ClientApi|false
     */
    public function getApiClient(StoreInterface $store): ClientApi|bool
    {
        try {
            $clientId = $this->subApiService->getClientId($store, $this);
            $clientSecret = $this->subApiService->getClientSecret($store, $this);
            $apiUrl = $this->subApiService->getApiUrl($store, $this);

            if (empty($clientId) || empty($clientSecret) || empty($apiUrl)) {
                return false;
            }

            if (! $this->subApiService->isTokenExpired($store, $this) && isset($this->cachedClient[$clientId])) {
                if (getenv('APSIS_DEVELOPER')) {
                    $this->debug('apiClient from cache.', ['Client Id' => $clientId, 'Store Id' => $store->getId()]);
                }
                return $this->cachedClient[$clientId];
            }

            $apiClient = $this->getClientApiModel()
                ->setHostName($apiUrl)
                ->setClientCredentials($clientId, $clientSecret)
                ->setService($this);

            $token = $this->subApiService->getToken($apiClient, $store, $this);
            if (empty($token)) {
                return false;
            }

            $apiClient->setToken($token);
            return $this->cachedClient[$clientId] = $apiClient;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param ClientApi $client
     * @param string $sectionDiscriminator
     *
     * @return array
     */
    public function getAttributeVersionIds(ClientApi $client, string $sectionDiscriminator): array
    {
        $attributesArr = [];
        try {
            $attributes = $client->getAttributes($sectionDiscriminator);
            if ($attributes && isset($attributes->items)) {
                foreach ($attributes->items as $attribute) {
                    foreach ($attribute->versions as $version) {
                        if ($version->deprecated_at === null) {
                            $attributesArr[$attribute->discriminator] = $version->id;
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
        return $attributesArr;
    }

    /**
     * @param ClientApi $client
     * @param string $section
     * @param array $eventsVersionMapping
     *
     * @return array
     */
    public function getAllEventVersionIds(ClientApi $client, string $section, array $eventsVersionMapping): array
    {
        try {
            $eventDefinition = $client->getEvents($section);
            if ($eventDefinition && isset($eventDefinition->items)) {
                foreach ($eventDefinition->items as $item) {
                    if (! array_key_exists($item->discriminator, $eventsVersionMapping)) {
                        continue;
                    }

                    foreach ($item->versions as $version) {
                        if ($version->deprecated_at === null) {
                            $eventsVersionMapping[$item->discriminator] = $version->id;
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
        return $eventsVersionMapping;
    }

    /**
     * @param StoreInterface $store
     * @param ProfileModel $profile
     * @param CustomerInterface $customer
     *
     * @return void
     */
    public function mergeProfile(StoreInterface $store, ProfileModel $profile, CustomerInterface $customer): void
    {
        try {
            $sectionDiscriminator = $this->getStoreConfig($store, BaseService::PATH_APSIS_CONFIG_SECTION);
            $integrationKeySpace = $this->getStoreConfig($store, BaseService::PATH_APSIS_CONFIG_KEYSPACE);
            if (empty($sectionDiscriminator) || empty($integrationKeySpace)) {
                return;
            }

            $apiClient = $this->getApiClient($store);
            if (empty($apiClient)) {
                return;
            }

            $keySpacesToMerge = $this->getKeySpacesToMerge($profile, $integrationKeySpace);
            if (empty($keySpacesToMerge)) {
                return;
            }

            if ($this->syncProfile($apiClient, $sectionDiscriminator, $profile, $customer, $integrationKeySpace)) {
                //If conflict on merge then set new cookie value for web keyspace
                if ($apiClient->mergeProfile($keySpacesToMerge) === Api\AbstractRestApi::HTTP_CODE_CONFLICT) {
                    //Log it
                    $this->debug(__METHOD__, ['Message' => 'Conflict, creating new cookie.']);

                    //Create new cookie value
                    $keySpacesToMerge[1]['profile_key'] = md5($profile->getId() . date('U'));

                    //Send second merge request
                    if ($apiClient->mergeProfile($keySpacesToMerge) === null) {
                        $this->setNewCookieValue($keySpacesToMerge, $store);
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
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
            $this->logError(__METHOD__, $e);
        }
        return $keySpacesToMerge;
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
                $this->debug(__METHOD__, $info);
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ClientApi $client
     * @param string $sectionDiscriminator
     * @param ProfileModel $profile
     * @param CustomerInterface $customer
     * @param string $integrationKeySpace
     *
     * @return bool
     */
    private function syncProfile(
        ClientApi $client,
        string $sectionDiscriminator,
        ProfileModel $profile,
        CustomerInterface $customer,
        string $integrationKeySpace
    ): bool {
        try {
            //If attribute version id array is empty, return false
            if (empty($attrArrWithVersionIds = $this->getAttributeVersionIds($client, $sectionDiscriminator))) {
                return false;
            }

            //Minimum, Email is needed
            if (! empty($attrArrWithVersionIds[BaseService::EMAIL_DISCRIMINATOR])) {
                $attributesToSync = [$attrArrWithVersionIds[BaseService::EMAIL_DISCRIMINATOR] => $customer->getEmail()];
                $status = $client->addAttributesToProfile(
                    $integrationKeySpace,
                    $profile->getId(),
                    $sectionDiscriminator,
                    $attributesToSync
                );
                return $status === null;
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
        return false;
    }
}
