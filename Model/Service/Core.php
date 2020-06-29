<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use stdClass;
use Apsis\One\Logger\Logger;
use Apsis\One\ApiClient\ClientFactory;
use Apsis\One\ApiClient\Client;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as DataCollectionFactory;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as DataCollection;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use libphonenumber\PhoneNumberUtil;

class Core extends ApsisLogHelper
{
    /**
     * APSIS table names
     */
    const APSIS_PROFILE_TABLE = 'apsis_profile';
    const APSIS_PROFILE_BATCH_TABLE = 'apsis_profile_batch';
    const APSIS_EVENT_TABLE = 'apsis_event';
    const APSIS_ABANDONED_TABLE = 'apsis_abandoned';

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
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param WriterInterface $writer
     * @param ClientFactory $clientFactory
     * @param DataCollectionFactory $dataCollectionFactory
     * @param ApsisDateHelper $apsisDateHelper
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        WriterInterface $writer,
        ClientFactory $clientFactory,
        DataCollectionFactory $dataCollectionFactory,
        ApsisDateHelper $apsisDateHelper,
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->request = $request;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->dataCollectionFactory = $dataCollectionFactory;
        $this->apiClientFactory = $clientFactory;
        $this->writer = $writer;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        parent::__construct($logger, $scopeConfig);
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
            $this->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            $this->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
        return $this->scopeConfig->getValue($path, $contextScope, $contextScopeId);
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
        $this->writer->save($path, $value, $contextScope, $contextScopeId);
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
        $this->writer->delete($path, $contextScope, $contextScopeId);
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
     * @return StoreInterface[]
     */
    public function getStores(bool $withDefault = false)
    {
        return $this->storeManager->getStores($withDefault);
    }

    /**
     * @param StoreInterface $store
     * @param string $path
     *
     * @return mixed
     */
    public function getStoreConfig(StoreInterface $store, string $path)
    {
        return $store->getConfig($path);
    }

    /**
     * @param float $price
     * @param int $precision
     *
     * @return float
     */
    public function round($price, int $precision = 2)
    {
        return (float) round($price, $precision);
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
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return string
     */
    private function getTokenFromDb(string $contextScope, int $scopeId)
    {
        $collection = $this->getDataCollectionByContextAndPath(
            $contextScope,
            $scopeId,
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN
        );
        $token = $this->encryptor->decrypt($collection->getFirstItem()->getValue());
        return $token;
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     * @param string $id
     * @param string $secret
     *
     * @return string
     */
    public function getTokenFromApi(string $contextScope, int $scopeId, $id = '', $secret = '')
    {
        try {
            $clientId = ($id) ? $id : $this->getClientId($contextScope, $scopeId);
            $clientSecret = ($secret) ? $secret : $this->getClientSecret($contextScope, $scopeId);
            if (! empty($clientId) && ! empty($clientSecret)) {
                /** @var Client $apiClient */
                $apiClient = $this->apiClientFactory->create();
                $request = $apiClient->getAccessToken($clientId, $clientSecret);

                if ($request && isset($request->access_token)) {
                    $scopeArray = $this->resolveContext($contextScope, $scopeId);
                    $contextScope = $scopeArray['scope'];
                    $scopeId = $scopeArray['id'];
                    $this->saveTokenAndExpiry($contextScope, $scopeId, $request);
                    return $request->access_token;
                }
            }
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return '';
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return string
     */
    private function getToken(string $contextScope, int $scopeId)
    {
        $scopeArray = $this->resolveContext($contextScope, $scopeId);
        $contextScope = $scopeArray['scope'];
        $scopeId = $scopeArray['id'];
        if ($this->isTokenExpired($contextScope, $scopeId)) {
            return $this->getTokenFromApi($contextScope, $scopeId);
        } else {
            $token = $this->getTokenFromDb($contextScope, $scopeId);
            return ($token) ? $token : $this->getTokenFromApi($contextScope, $scopeId);
        }
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return bool
     */
    private function isTokenExpired(string $contextScope, int $scopeId)
    {
        $expiryTime = '';
        $dataCollection = $this->getDataCollectionByContextAndPath(
            $contextScope,
            $scopeId,
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE
        );
        if ($dataCollection->getSize()) {
            $expiryTime = $dataCollection->getFirstItem()->getValue();
        }
        $nowTime = $this->apsisDateHelper->getDateTimeFromTimeAndTimeZone()->format('Y-m-d H:i:s');
        return ($nowTime > $expiryTime);
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return Client|bool
     */
    public function getApiClient(string $contextScope, int $scopeId)
    {
        if (! $this->isEnabled($contextScope, $scopeId)) {
            return false;
        }

        $token = $this->getToken($contextScope, $scopeId);
        if (empty($token)) {
            return false;
        }

        return $this->getApiClientFromToken($token);
    }

    /**
     * @param string $token
     *
     * @return Client
     */
    public function getApiClientFromToken(string $token)
    {
        /** @var Client $apiClient */
        $apiClient = $this->apiClientFactory->create();
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
            ->add($this->apsisDateHelper->getDateIntervalFromIntervalSpec(sprintf('PT%sS', $request->expires_in)));
        $this->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE,
            $time->format('Y-m-d H:i:s'),
            $contextScope,
            $scopeId
        );
    }

    /**
     * @param string $contextScope
     * @param int $scopeId
     *
     * @return array
     */
    private function resolveContext(string $contextScope, int $scopeId)
    {
        switch ($contextScope) {
            case ScopeInterface::SCOPE_STORES:
                return $this->resolveContextForStore($scopeId);
            case ScopeInterface::SCOPE_WEBSITES:
                return $this->resolveContextForWebsite($scopeId);
            default:
                return ['scope' => $contextScope, 'id' => $scopeId];
        }
    }

    /**
     * @param int $scopeId
     *
     * @return array
     */
    private function resolveContextForStore(int $scopeId)
    {
        $path = 'apsis_one_accounts/oauth/id';
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
     *
     * @return array
     */
    private function resolveContextForWebsite(int $scopeId)
    {
        $path = 'apsis_one_accounts/oauth/id';
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
    private function getDataCollectionByContextAndPath(string $contextScope, int $scopeId, string $path)
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
     * @param int $websiteId
     *
     * @return mixed
     */
    public function getAllStoreIdsFromWebsite(int $websiteId)
    {
        try {
            return $this->storeManager->getWebsite($websiteId)->getStoreIds();
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return [];
        }
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
        $hash = substr(md5($sectionDiscriminator), 0, 8);
        return "com.apsis1.integrations.keyspaces.$hash.magento";
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
     * @param string $countryCode
     * @param string $phoneNumber
     *
     * @return int|null
     */
    public function validateAndFormatMobileNumber(string $countryCode, string $phoneNumber)
    {
        $formattedNumber = null;
        try {
            if (strlen($countryCode) === 2) {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $numberProto = $phoneUtil->parse($phoneNumber, $countryCode);
                if ($phoneUtil->isValidNumber($numberProto)) {
                    $formattedNumber = (int) sprintf(
                        "%d%d",
                        (int) $numberProto->getCountryCode(),
                        (int) $numberProto->getNationalNumber()
                    );
                }
            }
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $formattedNumber;
    }
}