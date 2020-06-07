<?php

namespace Apsis\One\Helper;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Model\DateIntervalFactory;
use Apsis\One\Model\DateTimeFactory;
use Apsis\One\Model\DateTimeZoneFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Stdlib\StringUtils;
use stdClass;
use Zend_Date;
use Apsis\One\Logger\Logger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Apsis\One\ApiClient\ClientFactory;
use Apsis\One\ApiClient\Client;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as DataCollectionFactory;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as DataCollection;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;

class Core extends AbstractHelper
{
    /**
     * APSIS table names
     */
    const APSIS_PROFILE_TABLE = 'apsis_profile';
    const APSIS_PROFILE_BATCH_TABLE = 'apsis_profile_batch';
    const APSIS_EVENT_TABLE = 'apsis_event';
    const APSIS_ABANDONED_TABLE = 'apsis_abandoned';

    const APSIS_ATTRIBUTE_TYPE_TEXT_LIMIT = 100;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StringUtils
     */
    private $stringUtils;

    /**
     * @var TimezoneInterface
     */
    private $localeDate;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var Image
     */
    private $imageHelper;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var WriterInterface
     */
    private $writer;

    /**
     * @var ClientFactory
     */
    private $apiClientFactory;

    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * @var DateTimeZoneFactory
     */
    private $dateTimeZoneFactory;

    /**
     * @var DateIntervalFactory
     */
    private $dateIntervalFactory;

    /**
     * @var DataCollectionFactory
     */
    private $dataCollectionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * Core constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param StringUtils $stringUtils
     * @param TimezoneInterface $localeDate
     * @param EncryptorInterface $encryptor
     * @param Logger $logger
     * @param Image $imageHelper
     * @param ProductRepositoryInterface $productRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param WriterInterface $writer
     * @param ClientFactory $clientFactory
     * @param DateTimeFactory $dateTimeFactory
     * @param DateTimeZoneFactory $dateTimeZoneFactory
     * @param DateIntervalFactory $dateIntervalFactory
     * @param DataCollectionFactory $dataCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        StringUtils $stringUtils,
        TimezoneInterface $localeDate,
        EncryptorInterface $encryptor,
        Logger $logger,
        Image $imageHelper,
        ProductRepositoryInterface $productRepository,
        CustomerRepositoryInterface $customerRepository,
        WriterInterface $writer,
        ClientFactory $clientFactory,
        DateTimeFactory $dateTimeFactory,
        DateTimeZoneFactory $dateTimeZoneFactory,
        DateIntervalFactory $dateIntervalFactory,
        DataCollectionFactory $dataCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory
    ) {
        $this->dataCollectionFactory = $dataCollectionFactory;
        $this->dateIntervalFactory = $dateIntervalFactory;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->dateTimeZoneFactory = $dateTimeZoneFactory;
        $this->apiClientFactory = $clientFactory;
        $this->writer = $writer;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
        $this->localeDate = $localeDate;
        $this->storeManager = $storeManager;
        $this->stringUtils = $stringUtils;
        $this->profileCollectionFactory = $profileCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @param string $email
     * @param int $storeId
     *
     * @return bool|DataObject
     */
    public function getProfileByEmailAndStoreId(string $email, int $storeId)
    {
        return $this->profileCollectionFactory->create()
            ->loadByEmailAndStoreId($email, $storeId);
    }

    /**
     * @param int $customerId
     * @return bool|CustomerInterface
     */
    public function getCustomerById(int $customerId)
    {
        try {
            return $this->customerRepository->getById($customerId);
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage());
            return false;
        }
    }

    /**
     * @param int $productId
     * @return bool|ProductInterface
     */
    public function getProductById(int $productId)
    {
        try {
            return $this->productRepository->getById($productId);
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage());
            return false;
        }
    }

    /**
     * @param ProductInterface $product
     * @param string $imageId
     *
     * @return string
     */
    public function getProductImageUrl(ProductInterface $product, string $imageId = 'small_image')
    {
        $image = $this->imageHelper
            ->init($product, $imageId)
            ->setImageFile($product->getSmallImage());

        return $image->getUrl();
    }

    /**
     * @param string|int|float|bool|array|null $data
     * @return string|bool
     */
    public function serialize($data)
    {
        try {
            return json_encode($data);
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage());
            return '{}';
        }
    }

    /**
     * @param string $string
     *
     * @return array|bool|float|int|mixed|string|null|object
     */
    public function unserialize(string $string)
    {
        try {
            return json_decode($string);
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage());
            return [];
        }
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
            $this->logMessage(__METHOD__, $e->getMessage());
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
            $this->logMessage(__METHOD__, $e->getMessage());
            return '';
        }
    }

    /**
     * @param string $classMethodName
     * @param string $text
     */
    public function logMessage(string $classMethodName, string $text)
    {
        $this->log($this->getStringForLog($classMethodName, $text));
    }

    /**
     * INFO (200): Interesting events.
     *
     * @param string $message
     * @param array $extra
     */
    public function log(string $message, $extra = [])
    {
        $this->logger->info($message, $extra);
    }

    /**
     * DEBUG (100): Detailed debug information.
     *
     * @param string $message
     * @param array $extra
     */
    public function debug(string $message, $extra = [])
    {
        $this->logger->debug($message, $extra);
    }

    /**
     * ERROR (400): Runtime errors.
     *
     * @param string $message
     * @param array $extra
     */
    public function error(string $message, $extra = [])
    {
        $this->logger->error($message, $extra);
    }

    /**
     * Get selected scope in admin
     *
     * @return array
     */
    public function getSelectedScopeInAdmin()
    {
        $scope = [];
        $storeId = $this->_request->getParam('store');
        if ($storeId) {
            $scope['context_scope'] = ScopeInterface::SCOPE_STORES;
            $scope['context_scope_id'] = $storeId;
            return $scope;
        }

        $websiteId = $this->_request->getParam('website', 0);
        $contextScope = ($websiteId) ? ScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

        $scope['context_scope'] = $contextScope;
        $scope['context_scope_id'] = $websiteId;
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
     * @return string
     */
    public function generateBaseUrlForDynamicContent()
    {
        try {
            $website = $this->storeManager->getWebsite($this->_request->getParam('website', 0));
            $defaultGroup = $website->getDefaultGroup();
            $store =  (! $defaultGroup) ? null : $defaultGroup->getDefaultStore();
            return $this->storeManager->getStore($store)->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage());
            return '';
        }
    }

    /**
     * @param string $functionName
     * @param string $text
     *
     * @return string
     */
    public function getStringForLog(string $functionName, string $text)
    {
        return ' - Class & Method: ' . $functionName . ' - Text: ' . $text;
    }

    /**
     *  Check string length and limit to set in class constant.
     *
     * @param string $string
     *
     * @return string
     */
    public function limitStringLength($string)
    {
        if ($this->stringUtils->strlen($string) > self::APSIS_ATTRIBUTE_TYPE_TEXT_LIMIT) {
            $string = $this->stringUtils->substr($string, 0, self::APSIS_ATTRIBUTE_TYPE_TEXT_LIMIT);
        }

        return $string;
    }

    /**
     * @param string|null $date
     * @param string $format
     *
     * @return string|int
     */
    public function formatDateForPlatformCompatibility($date = null, $format = Zend_Date::TIMESTAMP)
    {
        return $this->localeDate->date($date)->format($format);
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
        $clientId = ($id) ? $id : $this->getClientId($contextScope, $scopeId);
        $clientSecret = ($secret) ? $secret : $this->getClientSecret($contextScope, $scopeId);
        if (! empty($clientId) && ! empty($clientSecret)) {
            /** @var Client $apiClient */
            $apiClient = $this->apiClientFactory->create();
            $request = $apiClient->setHelper($this)
                ->getAccessToken($clientId, $clientSecret);

            if ($request && isset($request->access_token)) {
                $scopeArray = $this->resolveContext($contextScope, $scopeId);
                $contextScope = $scopeArray['scope'];
                $scopeId = $scopeArray['id'];
                $this->saveTokenAndExpiry($contextScope, $scopeId, $request);
                return $request->access_token;
            }
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
        $nowTime = $this->dateTimeFactory->create(
            [
                'time' => 'now',
                'timezone' => $this->dateTimeZoneFactory->create(['timezone' => 'UTC'])
            ]
        )->format('Y-m-d H:i:s');
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
        return $apiClient->setHelper($this)
            ->setToken($token);
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

        $time = $this->dateTimeFactory->create(
            [
                'time' => 'now',
                'timezone' => $this->dateTimeZoneFactory->create(['timezone' => 'UTC'])
            ]
        )->add($this->dateIntervalFactory->create(['interval_spec' => sprintf('PT%sS', $request->expires_in)]));
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
     * @param string $inputDateTime
     *
     * @return bool
     */
    public function isExpired(string $inputDateTime)
    {
        $nowDateTime = $this->dateTimeFactory->create(
            [
                'time' => 'now',
                'timezone' => $this->dateTimeZoneFactory->create(['timezone' => 'UTC'])
            ]
        )->format(Zend_Date::ISO_8601);
        return ($nowDateTime > $inputDateTime);
    }

    /**
     * @param string $inputDateTime
     * @param int $day
     *
     * @return string
     */
    public function getFormattedDateTimeWithAddedInterval(string $inputDateTime, int $day = 1)
    {
        $interval = $this->dateIntervalFactory->create(
            ['interval_spec' => sprintf('P%sD', $day)]
        );
        $fromTime = $this->dateTimeFactory->create(
            [
                'time' => $inputDateTime,
                'timezone' => $this->dateTimeZoneFactory->create(['timezone' => 'UTC'])
            ]
        );
        $fromTime->add($interval);
        return $fromTime->format(Zend_Date::ISO_8601);
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public function isClean(string $string)
    {
        return ! preg_match("/[^a-zA-Z\d-]/i", $string);
    }
}
