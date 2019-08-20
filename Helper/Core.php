<?php

namespace Apsis\One\Helper;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use \Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Stdlib\StringUtils;
use Zend_Date;
use Apsis\One\Logger\Logger;

class Core extends AbstractHelper
{
    /**
     * APSIS table names
     */
    const APSIS_SUBSCRIBER_TABLE = 'apsis_subscriber';
    const APSIS_EVENT_TABLE = 'apsis_event';
    const APSIS_ABANDONED_TABLE = 'apsis_abandoned';

    /**
     * @var Logger
     */
    private $logger;

    /**
     * APSIS attribute type text limit
     */
    const APSIS_ATTRIBUTE_TYPE_TEXT_LIMIT = 100;

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
     * @var Random
     */
    private $random;

    /**
     * Core constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param StringUtils $stringUtils
     * @param TimezoneInterface $localeDate
     * @param EncryptorInterface $encryptor
     * @param Random $random
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        StringUtils $stringUtils,
        TimezoneInterface $localeDate,
        EncryptorInterface $encryptor,
        Random $random,
        Logger $logger
    ) {
        $this->logger = $logger;
        $this->encryptor = $encryptor;
        $this->localeDate = $localeDate;
        $this->storeManager = $storeManager;
        $this->stringUtils = $stringUtils;
        $this->random = $random;
        parent::__construct($context);
    }

    /**
     * @param string $className
     * @param string $methodName
     * @param string $text
     */
    public function logMessage(string $className, string $methodName, string $text)
    {
        $this->log($this->getStringForLog($className, $methodName, $text));
    }

    /**
     * INFO (200): Interesting events.
     *
     * @param string $data
     * @param array $extra
     */
    public function log($data, $extra = [])
    {
        $this->logger->info($data, $extra);
    }

    /**
     * DEBUG (100): Detailed debug information.
     *
     * @param string $message
     * @param array $extra
     */
    public function debug($message, $extra = [])
    {
        $this->logger->debug($message, $extra);
    }

    /**
     * ERROR (400): Runtime errors.
     *
     * @param string $message
     * @param array $extra
     */
    public function error($message, $extra = [])
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
            $scope['context_scope'] = 'stores';
            $scope['context_scope_id'] = $storeId;
            return $scope;
        }

        $websiteId = $this->_request->getParam('website', 0);
        $contextScope = ($websiteId) ? 'websites' : 'default';

        $scope['context_scope'] = $contextScope;
        $scope['context_scope_id'] = $websiteId;
        return $scope;
    }

    /**
     * @return bool
     */
    public function isEnabledForSelectedScopeInAdmin()
    {
        return (boolean) $this->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED
        );
    }

    /**
     * Get config scope value.
     *
     * @param string $path
     * @param string $contextScope
     * @param null|int $contextScopeId
     *
     * @return mixed
     */
    public function getConfigValue(string $path, string $contextScope = 'default', $contextScopeId = null)
    {
        return $this->scopeConfig->getValue($path, $contextScope, $contextScopeId);
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
            $this->logMessage(__CLASS__, __METHOD__, $e->getMessage());
            return '';
        }
    }

    /**
     * @param string $className
     * @param string $functionName
     * @param string $text
     *
     * @return string
     */
    public function getStringForLog(string $className, string $functionName, string $text)
    {
        return 'Class: ' . $className . ' - Method: ' . $functionName . ' - Text: ' . $text;
    }

    /**
     * @param int $length
     * @return string
     */
    public function getRandomString(int $length = 32)
    {
        try {
            return $this->random->getRandomString($length);
        } catch (Exception $e) {
            $this->logMessage(__CLASS__, __METHOD__, $e->getMessage());
            return rand();
        }
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
     * @param string $date
     *
     * @return string
     */
    public function formatDateForPlatformCompatibility($date)
    {
        return $this->localeDate->date($date)->format(Zend_Date::ISO_8601);
    }

    /**
     * Get all stores.
     *
     * @param bool|false $default
     *
     * @return StoreInterface[]
     */
    public function getStores($default = false)
    {
        return $this->storeManager->getStores($default);
    }

    /**
     * @param StoreInterface $store
     * @param string $path
     *
     * @return null|string
     */
    public function getStoreConfig(StoreInterface $store, $path)
    {
        return $store->getConfig($path);
    }
}
