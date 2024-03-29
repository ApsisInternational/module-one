<?php

namespace Apsis\One\Service;

use Apsis\One\Logger\Logger;
use Apsis\One\Model\ConfigModel;
use Apsis\One\Service\Api\ClientApi;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\Cookie\PhpCookieManagerFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\Api\ClientApiFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Apsis\One\Service\Api\AbstractRestApi;
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
     * @var ConfigService
     */
    public ConfigService $configService;

    /**
     * @var array
     */
    private array $cachedClient = [];

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param ModuleListInterface $moduleList
     * @param ClientApiFactory $clientFactory
     * @param PhpCookieManagerFactory $phpCookieManager
     * @param PublicCookieMetadataFactory $cookieMetadataFactory
     * @param ConfigService $configService
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        ModuleListInterface $moduleList,
        ClientApiFactory $clientFactory,
        PhpCookieManagerFactory $phpCookieManager,
        PublicCookieMetadataFactory $cookieMetadataFactory,
        ConfigService $configService
    ) {
        parent::__construct($logger, $storeManager, $writer, $moduleList);
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->phpCookieManager = $phpCookieManager;
        $this->clientFactory = $clientFactory;
        $this->configService = $configService;
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
     * @param ConfigModel $configModel
     *
     * @return ClientApi|bool
     */
    public function getApiClient(StoreInterface $store, ConfigModel $configModel): ClientApi|bool
    {
        try {
            $clientId = $configModel->getApiConfig()->getClientId();
            $clientSecret = $configModel->getApiConfig()->getClientSecret();
            $apiUrl = $configModel->getApiConfig()->getApiUrl();
            if (empty($clientId) || empty($clientSecret) || empty($apiUrl)) {
                return false;
            }

            if (isset($this->cachedClient[$clientId])) {
                if (getenv('APSIS_DEVELOPER')) {
                    $this->debug('apiClient from cache.', ['Client Id' => $clientId, 'Store Id' => $store->getId()]);
                }
                return $this->cachedClient[$clientId];
            }

            $apiClient = $this->getClientApiModel()
                ->setHostName($apiUrl)
                ->setClientCredentials($clientId, $clientSecret)
                ->setService($this);

            $token = $this->getToken($apiClient, $configModel);
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
            $configModel = $this->configService->getActiveConfigForStore($store->getId());
            if (empty($configModel) || empty($configModel->getApiConfig())) {
                return;
            }

            $sectionDiscriminator = $configModel->getApiConfig()->getSectionDiscriminator();
            $integrationKeySpace = $configModel->getApiConfig()->getKeyspaceDiscriminator();
            if (empty($sectionDiscriminator) || empty($integrationKeySpace)) {
                return;
            }

            $apiClient = $this->getApiClient($store, $configModel);
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
                    if (getenv('APSIS_DEVELOPER')) {
                        $this->debug(__METHOD__, ['Message' => 'Conflict, creating new cookie.']);
                    }

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

                if (getenv('APSIS_DEVELOPER')) {
                    $info = ['Name' => self::APSIS_WEB_COOKIE_NAME, 'Value' => $keySpacesToMerge[1]['profile_key']];
                    $this->debug(__METHOD__, $info);
                }
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

    /**
     * @param ClientApi $apiClient
     * @param ConfigModel $configModel
     *
     * @return string
     */
    private function getToken(ClientApi $apiClient, ConfigModel $configModel): string
    {
        try {
            $token = $configModel->getApiToken();
            if (empty($token) || $this->isTokenExpired($configModel)) {
                $response = $apiClient->getAccessToken();
                // Successfully renewed
                if ($response && isset($response->access_token)) {
                    if (getenv('APSIS_DEVELOPER')) {
                        $this->debug('Token renewed', ['Store Id' => $configModel->getStoreId()]);
                    }
                    return $this->configService->saveApiTokenAndExpiry($configModel, $response);
                }

                // Error in generating token
                if ($response && isset($response->status) &&
                    in_array($response->status, AbstractRestApi::HTTP_CODES_DISABLE)
                ) {
                    if (getenv('APSIS_DEVELOPER')) {
                        $this->debug('Unable to renew token', (array) $response);
                    }
                    $this->configService->markConfigInactive($configModel, $response);
                }
            }
            return $token;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param ConfigModel $configModel
     *
     * @return bool
     */
    private function isTokenExpired(ConfigModel $configModel): bool
    {
        try {
            $expiryTime = $configModel->getApiTokenExpiry();
            $nowTime = $this->getDateTimeFromTimeAndTimeZone()
                ->add($this->getDateIntervalFromIntervalSpec('PT15M'))
                ->format('Y-m-d H:i:s');

            $check = ($nowTime > $expiryTime);
            if ($check && getenv('APSIS_DEVELOPER')) {
                $info = [
                    'Store Id' => $configModel->getStoreId(),
                    'Is Expired/Empty' => true,
                    'Last Expiry DateTime' => $expiryTime
                ];
                $this->debug(__METHOD__, $info);
            }

            return $check;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return true;
        }
    }
}
