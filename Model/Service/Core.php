<?php

namespace Apsis\One\Model\Service;

use Apsis\One\ApiClient\Client;
use Apsis\One\ApiClient\ClientFactory;
use Apsis\One\Logger\Logger;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use stdClass;
use Throwable;

class Core extends ApsisLogHelper
{
    // DB Tables
    const APSIS_PROFILE_TABLE = 'apsis_profile';
    const APSIS_EVENT_TABLE = 'apsis_event';
    const APSIS_ABANDONED_TABLE = 'apsis_abandoned';

    // Config keys
    const PATH_INTEGRATION_API_KEY = 'apsis_one_connect/api/key';
    const PATH_CONFIG_AC_DURATION = 'apsis_one_configuration/abandoned_cart/duration';
    const PATH_CONFIG_PROFILE_ORDER_STATUS = 'apsis_one_configuration/profile_sync/order_status';
    const PATH_CONFIG_TRACKING_SCRIPT = 'apsis_one_configuration/tracking/script';

    const PATH_APSIS_CLIENT_ID = 'apsis_one/api/client_id';
    const PATH_APSIS_CLIENT_SECRET = 'apsis_one/api/client_secret';
    const PATH_APSIS_API_URL = 'apsis_one/api/url';
    const PATH_APSIS_API_TOKEN = 'apsis_one/api/token';
    const PATH_APSIS_API_TOKEN_EXPIRY = 'apsis_one/api/token_expiry';
    const PATH_APSIS_CONFIG_SECTION = 'apsis_one/config/section';
    const PATH_APSIS_CONFIG_PROFILE_KEY = 'apsis_one/config/profile_key';

    const CONFIG_PATHS_SECURE = [
        self::PATH_INTEGRATION_API_KEY,
        self::PATH_APSIS_CLIENT_ID,
        self::PATH_APSIS_CLIENT_SECRET,
        self::PATH_APSIS_API_TOKEN
    ];

    // APSIS configs
    const EMAIL_DISCRIMINATOR = 'com.apsis1.attributes.email';

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var WriterInterface
     */
    public WriterInterface $writer;

    /**
     * @var ClientFactory
     */
    private ClientFactory $apiClientFactory;

    /**
     * @var ApsisDateHelper
     */
    private Date $apsisDateHelper;

    /**
     * @var array
     */
    private array $cachedClient = [];

    /**
     * Core constructor.
     *
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param WriterInterface $writer
     * @param ClientFactory $clientFactory
     * @param ApsisDateHelper $apsisDateHelper
     * @param ResourceInterface $moduleResource
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        WriterInterface $writer,
        ClientFactory $clientFactory,
        ApsisDateHelper $apsisDateHelper,
        ResourceInterface $moduleResource,
        ModuleListInterface $moduleList
    ) {
        $this->apsisDateHelper = $apsisDateHelper;
        $this->apiClientFactory = $clientFactory;
        $this->writer = $writer;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        parent::__construct($logger, $moduleResource, $moduleList);
    }

    /**
     * @param int|null $storeId
     *
     * @return bool|StoreInterface
     */
    public function getStore(int $storeId = null)
    {
        try {
            return $this->storeManager->getStore($storeId);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * Get all stores.
     *
     * @param bool $withDefault
     *
     * @return array
     */
    public function getStores(bool $withDefault = false): array
    {
        try {
            return $this->storeManager->getStores($withDefault);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param null|int $storeId
     *
     * @return string
     */
    public function getStoreName(int $storeId = null): string
    {
        $store = $this->getStore($storeId);
        return ($store) ? $store->getName() : '';
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    public function isStoreFrontSecure(int $storeId): bool
    {
        try {
            $store = $this->getStore($storeId);
            return $store instanceof StoreInterface && $store->isFrontUrlSecure();
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param int $storeId
     *
     * @return string
     */
    public function getStoreBaseUrl(int $storeId): string
    {
        try {
            $store = $this->getStore($storeId);
            return $store ? $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $this->isStoreFrontSecure($storeId)) : '';
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param null|int $storeId
     *
     * @return string
     */
    public function getStoreWebsiteName(int $storeId = null): string
    {
        try {
            $store = $this->getStore($storeId);
            return ($store) ? $this->storeManager->getWebsite($store->getWebsiteId())->getName() : '';
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $path
     *
     * @return string|null
     */
    public function getStoreConfig(StoreInterface $store, string $path)
    {
        try {
            return $store->getConfig($path);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $path
     * @param string $value
     *
     * @return void
     */
    public function saveStoreConfig(StoreInterface $store, string $path, string $value): void
    {
        try {
            $info = [
                'Store Id' => $store->getId(),
                'Config Path' => $path,
                'Old Value' => $this->getStoreConfig($store, $path),
                'New Value' => $value
            ];
            if (in_array($path, self::CONFIG_PATHS_SECURE)) {
                $info['Old Value'] = $info['New Value'] = 'An encrypted value.';
            }
            $this->debug(__METHOD__, $info);

            $this->writer->save($path, $value, ScopeInterface::SCOPE_STORE, $store->getId());
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param int|float $price
     * @param int $precision
     *
     * @return float
     */
    public function round(int|float $price, int $precision = 2): float
    {
        try {
            return round($price, $precision);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return 0.00;
        }
    }

    /**
     * @param mixed $data
     *
     * @return string|bool
     */
    public function serialize(mixed $data)
    {
        try {
            return json_encode($data);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '[]';
        }
    }

    /**
     * @param string $string
     *
     * @return mixed
     */
    public function unserialize(string $string): mixed
    {
        try {
            return json_decode($string);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * VALID RFC 4211 COMPLIANT Universally Unique Identifier (UUID) version 4
     * https://www.php.net/manual/en/function.uniqid.php#94959
     *
     * @return string
     */
    public static function generateUniversallyUniqueIdentifier(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * @param Client $client
     * @param string $sectionDiscriminator
     *
     * @return array
     */
    public function getAttributeVersionIds(Client $client, string $sectionDiscriminator): array
    {
        $attributesArr = [];

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

        return $attributesArr;
    }

    /**
     * @param StoreInterface $store
     *
     * @return Client|false
     */
    public function getApiClient(StoreInterface $store)
    {
        $clientId = $this->getStoreConfig($store, self::PATH_APSIS_CLIENT_ID);
        $clientSecret = $this->encryptor->decrypt($this->getStoreConfig($store, self::PATH_APSIS_CLIENT_SECRET));
        $apiUrl = $this->getStoreConfig($store, self::PATH_APSIS_API_URL);


        if (empty($clientId) || empty($clientSecret) || empty($apiUrl)) {
            $this->log(__METHOD__ . ' : Missing client credentials.');
            return false;
        }

        if (! $this->isTokenExpired($store) && isset($this->cachedClient[$clientId])) {
            if ((bool) getenv('APSIS_DEVELOPER')) {
                $this->debug("apiClient from cache.", ['Client Id' => $clientId, 'Store Id' => $store->getId()]);
            }

            return $this->cachedClient[$clientId];
        }

        $apiClient = $this->apiClientFactory->create()
            ->setHostName($apiUrl)
            ->setClientCredentials($clientId, $clientSecret)
            ->setHelper($this);

        $token = $this->getToken($apiClient, $store);

        if (empty($token)) {
            return false;
        }

        $apiClient->setToken($token);
        return $this->cachedClient[$clientId] = $apiClient;
    }

    /**
     * @param Client $apiClient
     * @param StoreInterface $store
     *
     * @return string
     */
    private function getToken(Client $apiClient, StoreInterface $store): string
    {
        $token = '';
        try {
            // First fetch from DB
            $token = $this->encryptor->decrypt($this->getStoreConfig($store, self::PATH_APSIS_API_TOKEN));
            if (empty($token) || $this->isTokenExpired($store)) {
                $response = $apiClient->getAccessToken();

                //Success in generating token
                if ($response && isset($response->access_token)) {
                    $this->debug('Token renewed', ['Store Id' => $store->getId()]);
                    $this->saveTokenAndExpiry($store, $response);
                    return (string) $response->access_token;
                }

                //Error in generating token
                if ($response && isset($response->status) &&
                    in_array($response->status, Client::HTTP_CODES_DISABLE_MODULE)
                ) {
                    $this->debug(__METHOD__, (array) $response);
                }
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
        return $token;
    }

    /**
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isTokenExpired(StoreInterface $store): bool
    {
        try {
            $expiryTime = $this->getStoreConfig($store, self::PATH_APSIS_API_TOKEN_EXPIRY);
            $nowTime = $this->apsisDateHelper->getDateTimeFromTimeAndTimeZone()
                ->add($this->apsisDateHelper->getDateIntervalFromIntervalSpec('PT15M'))
                ->format('Y-m-d H:i:s');

            $check = ($nowTime > $expiryTime);

            if ($check) {
                $info = [
                    'Store Id' => $store->getId(),
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

    /**
     * @param StoreInterface $store
     * @param stdClass $request
     *
     * @return void
     */
    private function saveTokenAndExpiry(StoreInterface $store, stdClass $request): void
    {
        try {
            $this->saveStoreConfig(
                $store,
                self::PATH_APSIS_API_TOKEN,
                $this->encryptor->encrypt($request->access_token)
            );

            $time = $this->apsisDateHelper
                ->getDateTimeFromTimeAndTimeZone()
                ->add($this->apsisDateHelper->getDateIntervalFromIntervalSpec(sprintf('PT%sS', $request->expires_in)))
                ->sub($this->apsisDateHelper->getDateIntervalFromIntervalSpec('PT60M'))
                ->format('Y-m-d H:i:s');

            $this->saveStoreConfig($store, self::PATH_APSIS_API_TOKEN_EXPIRY, $time);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }
}
