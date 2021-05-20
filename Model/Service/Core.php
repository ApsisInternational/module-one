<?php

namespace Apsis\One\Model\Service;

use Apsis\One\ApiClient\Client;
use Apsis\One\ApiClient\ClientFactory;
use Apsis\One\Logger\Logger;
use Apsis\One\Model\Config\Source\System\Region;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Exception;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as DataCollection;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as DataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use stdClass;

class Core extends ApsisLogHelper
{
    /**
     * APSIS table names
     */
    const APSIS_PROFILE_TABLE = 'apsis_profile';
    const APSIS_PROFILE_BATCH_TABLE = 'apsis_profile_batch';
    const APSIS_EVENT_TABLE = 'apsis_event';
    const APSIS_ABANDONED_TABLE = 'apsis_abandoned';

    const PRODUCTION_TLD = 'one';
    const STAGE_TLD = 'cloud';

    const EU_FILE_UPLOAD_URL = 'https://s3.eu-west-1.amazonaws.com';
    const APAC_FILE_UPLOAD_URL = 'https://s3.ap-southeast-1.amazonaws.com';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var WriterInterface
     */
    private $writer;

    /**
     * @var ClientFactory
     */
    private $apiClientFactory;

    /**
     * @var DataCollectionFactory
     */
    private $dataCollectionFactory;

    /**
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * Request object
     *
     * @var RequestInterface
     */
    private $request;

    /**
     * Core constructor.
     *
     * @param Logger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param WriterInterface $writer
     * @param ClientFactory $clientFactory
     * @param DataCollectionFactory $dataCollectionFactory
     * @param ApsisDateHelper $apsisDateHelper
     * @param RequestInterface $request
     * @param ResourceInterface $moduleResource
     */
    public function __construct(
        Logger $logger,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        WriterInterface $writer,
        ClientFactory $clientFactory,
        DataCollectionFactory $dataCollectionFactory,
        ApsisDateHelper $apsisDateHelper,
        RequestInterface $request,
        ResourceInterface $moduleResource
    ) {
        $this->request = $request;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->dataCollectionFactory = $dataCollectionFactory;
        $this->apiClientFactory = $clientFactory;
        $this->writer = $writer;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        parent::__construct($logger, $scopeConfig, $moduleResource);
    }

    /**
     * @param null|int $storeId
     * @return bool|StoreInterface
     */
    public function getStore($storeId = null)
    {
        try {
            return $this->storeManager->getStore($storeId);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param null|int $storeId
     * @return string
     */
    public function getStoreNameFromId($storeId = null)
    {
        $store = $this->getStore($storeId);
        return ($store) ? $store->getName() : '';
    }

    /**
     * @param null|int $storeId
     * @return string
     */
    public function getWebsiteNameFromStoreId($storeId = null)
    {
        try {
            $store = $this->getStore($storeId);
            return ($store) ? $this->storeManager->getWebsite($store->getWebsiteId())->getName() : '';
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * Get selected scope in admin
     *
     * @return array
     */
    public function getSelectedScopeInAdmin()
    {
        $scope = [];
        $storeId = $this->request->getParam('store');
        if ($storeId) {
            $scope['context_scope'] = ScopeInterface::SCOPE_STORES;
            $scope['context_scope_id'] = (int) $storeId;
            return $scope;
        }

        $websiteId = $this->request->getParam('website', 0);
        $contextScope = ($websiteId) ? ScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

        $scope['context_scope'] = $contextScope;
        $scope['context_scope_id'] = (int) $websiteId;
        return $scope;
    }

    /**
     * Get config scope value.
     *
     * @param string $path
     * @param string $contextScope
     * @param int $contextScopeId
     *
     * @return mixed
     */
    public function getConfigValue(string $path, string $contextScope, int $contextScopeId)
    {
        try {
            return $this->scopeConfig->getValue($path, $contextScope, $contextScopeId);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Save config scope value.
     *
     * @param string $path
     * @param string $value
     * @param string $contextScope
     * @param int $contextScopeId
     */
    public function saveConfigValue(
        string $path,
        string $value,
        string $contextScope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $contextScopeId = 0
    ) {
        try {
            $context = $this->getScopeForConfigUpdate($path, $contextScope, $contextScopeId);
            $this->writer->save($path, $value, $context['scope'], $context['id']);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * Delete config by scope.
     *
     * @param string $path
     * @param string $contextScope
     * @param int $contextScopeId
     */
    public function deleteConfigByScope(
        string $path,
        string $contextScope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $contextScopeId = 0
    ) {
        try {
            $context = $this->getScopeForConfigUpdate($path, $contextScope, $contextScopeId);
            $this->writer->delete($path, $context['scope'], $context['id']);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $path
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return array
     */
    private function getScopeForConfigUpdate(string $path, string $contextScope, int $scopeId)
    {
        if ($path == Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN ||
            $path == Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE ||
            $path == Config::CONFIG_APSIS_ONE_EVENTS_ORDER_HISTORY_DONE_FLAG ||
            $path == Config::CONFIG_APSIS_ONE_EVENTS_QUOTE_HISTORY_DONE_FLAG ||
            $path == Config::CONFIG_APSIS_ONE_EVENTS_REVIEW_HISTORY_DONE_FLAG ||
            $path == Config::CONFIG_APSIS_ONE_EVENTS_WISHLIST_HISTORY_DONE_FLAG

        ) {
            return $this->resolveContext($contextScope, $scopeId, $path);
        }

        return ['scope' => $contextScope, 'id' => $scopeId];
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getMappedValueFromSelectedScope(string $path)
    {
        $scope = $this->getSelectedScopeInAdmin();
        return $this->getConfigValue(
            $path,
            $scope['context_scope'],
            $scope['context_scope_id']
        );
    }

    /**
     * Get all stores.
     *
     * @param bool $withDefault
     *
     * @return StoreInterface[]|array
     */
    public function getStores(bool $withDefault = false)
    {
        try {
            return $this->storeManager->getStores($withDefault);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $path
     *
     * @return mixed
     */
    public function getStoreConfig(StoreInterface $store, string $path)
    {
        try {
            return $store->getConfig($path);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param float $price
     * @param int $precision
     *
     * @return float
     */
    public function round($price, int $precision = 2)
    {
        try {
            return (float) round($price, $precision);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return 0.00;
        }
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return mixed
     */
    public function getRegion(string $contextScope, int $scopeId)
    {
        return $this->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_REGION,
            $contextScope,
            $scopeId
        );
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return mixed
     */
    private function getClientId(string $contextScope, int $scopeId)
    {
        return $this->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ID,
            $contextScope,
            $scopeId
        );
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return string
     */
    private function getClientSecret(string $contextScope, int $scopeId)
    {
        $value = $this->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET,
            $contextScope,
            $scopeId
        );
        return $this->encryptor->decrypt($value);
    }

    /**
     * @return string
     */
    public function getSubscriptionEndpointKey()
    {
        $value = $this->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );
        return $this->encryptor->decrypt($value);
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return bool
     */
    public function isEnabled(string $contextScope, int $scopeId)
    {
        return (boolean) $this->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED,
            $contextScope,
            $scopeId
        );
    }

    /**
     * @param Client $apiClient
     * @param string $contextScope
     * @param int $scopeId
     * @param bool $bypassDb
     *
     * @return string
     */
    private function getToken(Client $apiClient, string $contextScope, int $scopeId, bool $bypassDb = false)
    {
        $token = '';
        try {
            if (! $bypassDb) {
                $token = $this->getTokenFromDb($contextScope, $scopeId);
            }

            if (empty($token) || $this->isTokenExpired($contextScope, $scopeId)) {
                $response = $apiClient->getAccessToken();

                //Success in generating token
                if ($response && isset($response->access_token)) {
                    $this->saveTokenAndExpiry($contextScope, $scopeId, $response);
                    return (string) $response->access_token;
                }

                //Error in generating token, disable module & remove token along with token expiry
                if ($response && isset($response->status) &&
                    in_array($response->status, Client::HTTP_CODES_DISABLE_MODULE)
                ) {
                    $this->log(__METHOD__ . ' : Http code ' . $response->status . ' on generating token.');
                    $this->disableAccountAndRemoveTokenConfig($contextScope, $scopeId);
                    return '';
                }
            }
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
        }
        return $token;
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return string
     */
    private function getTokenFromDb(string $contextScope, int $scopeId)
    {
        $token = '';
        $context = $this->resolveContext(
            $contextScope,
            $scopeId,
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN
        );
        $collection = $this->getDataCollectionByContextAndPath(
            $context['scope'],
            $context['id'],
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN
        );
        if ($collection->getSize()) {
            $token = $this->encryptor->decrypt($collection->getFirstItem()->getValue());
        }
        return $token;
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return string
     */
    private function getTokenExpiryFromDb(string $contextScope, int $scopeId)
    {
        $expiryTime = '';
        $context = $this->resolveContext(
            $contextScope,
            $scopeId,
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE
        );

        $collection = $this->getDataCollectionByContextAndPath(
            $context['scope'],
            $context['id'],
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE
        );
        if ($collection->getSize()) {
            $expiryTime = $collection->getFirstItem()->getValue();
        }

        return $expiryTime;
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     */
    public function disableAccountAndRemoveTokenConfig(string $contextScope, int $scopeId)
    {
        $this->log(__METHOD__);

        $this->removeTokenConfig($contextScope, $scopeId);
        $this->disableAccountOnContext($contextScope, $scopeId);
    }

    /**
     * @param string $contextScope
     *
     * @param int $contextScopeId
     */
    private function disableAccountOnContext(string $contextScope, int $contextScopeId)
    {
        $this->log(__METHOD__);

        $this->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED,
            0,
            $contextScope,
            $contextScopeId
        );
        $this->deleteConfigByScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ID,
            $contextScope,
            $contextScopeId
        );
        $this->deleteConfigByScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET,
            $contextScope,
            $contextScopeId
        );
        $this->deleteConfigByScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_REGION,
            $contextScope,
            $contextScopeId
        );
        $this->cleanCache();
    }

    /**
     * @param string $contextScope
     * @param int $contextScopeId
     */
    public function removeTokenConfig(string $contextScope, int $contextScopeId)
    {
        $this->log(__METHOD__);

        $this->deleteConfigByScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN,
            $contextScope,
            $contextScopeId
        );
        $this->deleteConfigByScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE,
            $contextScope,
            $contextScopeId
        );
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return bool
     */
    private function isTokenExpired(string $contextScope, int $scopeId)
    {
        $expiryTime = $this->getTokenExpiryFromDb($contextScope, $scopeId);

        if (empty($expiryTime)) {
            return true;
        }

        $nowTime = $this->apsisDateHelper->getDateTimeFromTimeAndTimeZone()
            ->add($this->apsisDateHelper->getDateIntervalFromIntervalSpec('PT15M'))
            ->format('Y-m-d H:i:s');

        return ($nowTime > $expiryTime);
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     * @param bool $bypassDb
     * @param string $region
     * @param string $clientId
     * @param string $clientSecret
     *
     * @return Client|false
     */
    public function getApiClient(
        string $contextScope,
        int $scopeId,
        bool $bypassDb = false,
        string $region = '',
        string $clientId = '',
        string $clientSecret = ''
    ) {
        if (! $bypassDb) {
            if (! $this->isEnabled($contextScope, $scopeId)) {
                return false;
            }

            if (empty($clientId)) {
                $clientId = $this->getClientId($contextScope, $scopeId);
            }

            if (empty($clientSecret)) {
                $clientSecret = $this->getClientSecret($contextScope, $scopeId);
            }

            if (empty($region)) {
                $region = $this->getRegion($contextScope, $scopeId);
            }

            if (empty($clientId) || empty($clientSecret) || empty($region)) {
                $this->log(__METHOD__ . ' : Missing client credentials.');
                $this->disableAccountAndRemoveTokenConfig($contextScope, $scopeId);
                return false;
            }
        } elseif (empty($clientId) || empty($clientSecret) || empty($region)) {
            $this->log(__METHOD__ . ' : Missing client credentials given $bypassDb variable.');
            return false;
        }

        $apiClient = $this->apiClientFactory->create()
            ->setHostName($this->buildHostName($region))
            ->setClientCredentials($clientId, $clientSecret)
            ->setHelper($this);

        $token = $this->getToken($apiClient, $contextScope, $scopeId, $bypassDb);

        if (empty($token)) {
            return false;
        }

        return $apiClient->setToken($token);
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     * @param stdClass $request
     */
    private function saveTokenAndExpiry(string $contextScope, int $scopeId, stdClass $request)
    {
        $this->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN,
            $this->encryptor->encrypt($request->access_token),
            $contextScope,
            $scopeId
        );

        $time = $this->apsisDateHelper
            ->getDateTimeFromTimeAndTimeZone()
            ->add($this->apsisDateHelper->getDateIntervalFromIntervalSpec(sprintf('PT%sS', $request->expires_in)))
            ->sub($this->apsisDateHelper->getDateIntervalFromIntervalSpec('PT60M'))
            ->format('Y-m-d H:i:s');

        $this->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE,
            $time,
            $contextScope,
            $scopeId
        );
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     * @param string $path
     *
     * @return array
     */
    public function resolveContext(string $contextScope, int $scopeId, string $path)
    {
        switch ($contextScope) {
            case ScopeInterface::SCOPE_STORES:
                return $this->resolveContextForStore($scopeId, $path);
            case ScopeInterface::SCOPE_WEBSITES:
                return $this->resolveContextForWebsite($scopeId, $path);
            default:
                return ['scope' => $contextScope, 'id' => $scopeId];
        }
    }

    /**
     * @param int $scopeId
     * @param string $path
     *
     * @return array
     */
    private function resolveContextForStore(int $scopeId, string $path)
    {
        $contextScope = ScopeInterface::SCOPE_STORES;
        if (! $this->isExistInDataCollection($contextScope, $scopeId, $path)) {
            $websiteId = (int) $this->getStore($scopeId)->getWebsiteId();
            if ($this->isExistInDataCollection(ScopeInterface::SCOPE_WEBSITES, $websiteId, $path)) {
                $contextScope = ScopeInterface::SCOPE_WEBSITES;
                $scopeId = $websiteId;
            } else {
                $contextScope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
                $scopeId = 0;
            }
        }
        return ['scope' => $contextScope, 'id' => $scopeId];
    }

    /**
     * @param int $scopeId
     * @param string $path
     *
     * @return array
     */
    private function resolveContextForWebsite(int $scopeId, string $path)
    {
        $contextScope = ScopeInterface::SCOPE_WEBSITES;
        if (! $this->isExistInDataCollection($contextScope, $scopeId, $path)) {
            $contextScope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeId = 0;
        }
        return ['scope' => $contextScope, 'id' => $scopeId];
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     * @param string $path
     *
     * @return bool
     */
    private function isExistInDataCollection(string $contextScope, int $scopeId, string $path)
    {
        $collection = $this->getDataCollectionByContextAndPath($contextScope, $scopeId, $path);
        return (boolean) $collection->getSize();
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     * @param string $path
     *
     * @return DataCollection
     */
    public function getDataCollectionByContextAndPath(string $contextScope, int $scopeId, string $path)
    {
        $collection = $this->dataCollectionFactory->create()
            ->addFieldToFilter('scope', $contextScope)
            ->addFieldToFilter('scope_id', $scopeId)
            ->addFieldToFilter('path', $path);
        $collection->getSelect()->limit(1);
        return $collection;
    }

    /**
     * @param bool $withDefault
     *
     * @return array
     */
    public function getAllStoreIds(bool $withDefault = false)
    {
        $storeIds = [];
        $stores = $this->getStores($withDefault);
        foreach ($stores as $store) {
            $storeIds[] = $store->getId();
        }
        return $storeIds;
    }

    /**
     * @param false $withDefault
     * @return array|WebsiteInterface[]
     */
    public function getAllWebsites($withDefault = false)
    {
        try {
            return $this->storeManager->getWebsites($withDefault);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param int $websiteId
     *
     * @return array
     */
    public function getAllStoreIdsFromWebsite(int $websiteId)
    {
        try {
            return $this->storeManager->getWebsite($websiteId)->getStoreIds();
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @return array
     */
    public function getStoreIdsBasedOnScope()
    {
        if ($storeId = $this->request->getParam('store')) {
            return [$storeId];
        }

        if ($websiteId = $this->request->getParam('website')) {
            return $this->getAllStoreIdsFromWebsite($websiteId);
        }

        return [];
    }

    /**
     * @return DataCollection
     */
    public function getConfigDataCollection()
    {
        return $this->dataCollectionFactory->create();
    }

    /**
     * @param string $sectionDiscriminator
     *
     * @return string
     */
    public function getKeySpaceDiscriminator(string $sectionDiscriminator)
    {
        try {
            $hash = substr(md5($sectionDiscriminator), 0, 8);
            return "com.apsis1.integrations.keyspaces.$hash.magento";
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param Client $client
     * @param string $sectionDiscriminator
     *
     * @return array
     */
    public function getAttributesArrWithVersionId(Client $client, string $sectionDiscriminator)
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
     * @return string
     */
    public function generateBaseUrlForDynamicContent()
    {
        try {
            $website = $this->storeManager->getWebsite($this->request->getParam('website', 0));
            $defaultGroup = $website->getDefaultGroup();
            $store =  (! $defaultGroup) ? null : $defaultGroup->getDefaultStore();
            return $this->storeManager->getStore($store)->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * VALID RFC 4211 COMPLIANT Universally Unique Identifier (UUID) version 4
     * https://www.php.net/manual/en/function.uniqid.php#94959
     *
     * @return string
     */
    public static function generateUniversallyUniqueIdentifier()
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
     * @param string $url
     *
     * @throws Exception
     */
    public function validateIsUrlReachable(string $url)
    {
        $ch = curl_init($url);

        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
        curl_exec($ch);

        if (curl_errno($ch)) {
            $msg = 'CURL ERROR: ' . curl_error($ch) . '. Unable to reach URL: ' . $url;
            curl_close($ch);

            throw new Exception($msg);
        }

        curl_close($ch);
    }

    /**
     * @param string $region
     *
     * @return string
     */
    public function buildHostName(string $region)
    {
        $tld = self::PRODUCTION_TLD;
        if ($region === Region::REGION_STAGE) {
            $tld = self::STAGE_TLD;
        }
        return sprintf('https://%s.apsis.%s', $region, $tld);
    }

    /**
     * @param string $region
     *
     * @return string
     */
    public function buildFileUploadHostName(string $region)
    {
        $url = self::EU_FILE_UPLOAD_URL;
        if ($region === Region::REGION_APAC) {
            $url = self::APAC_FILE_UPLOAD_URL;
        }
        return $url;
    }

    /**
     * @param string $scope
     *
     * @param int $scopeId
     */
    public function disableProfileSync(string $scope, int $scopeId)
    {
        $this->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED,
            0,
            $scope,
            $scopeId
        );
        $this->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED,
            0,
            $scope,
            $scopeId
        );
        $this->log('Profile sync disabled.');
    }
}
