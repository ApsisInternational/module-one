<?php

namespace Apsis\One\Helper;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\StringUtils;
use Zend_Date;

class Core extends AbstractHelper
{
    /**
     * APSIS table names
     */
    const APSIS_SUBSCRIBER_TABLE = 'apsis_subscriber';
    const APSIS_EVENT_TABLE = 'apsis_event';
    const APSIS_ABANDONED_TABLE = 'apsis_abandoned';

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
    protected $localeDate;

    /**
     * Core constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param StringUtils $stringUtils
     * @param TimezoneInterface $localeDate
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        StringUtils $stringUtils,
        TimezoneInterface $localeDate
    ) {
        $this->localeDate = $localeDate;
        $this->storeManager = $storeManager;
        $this->stringUtils = $stringUtils;
        parent::__construct($context);
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
     * @return mixed
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function generateBaseUrlForDynamicContent()
    {
        $website = $this->storeManager->getWebsite($this->_request->getParam('website', 0));
        $defaultGroup = $website->getDefaultGroup();
        $store =  (! $defaultGroup) ? null : $defaultGroup->getDefaultStore();
        return $this->storeManager->getStore($store)->getBaseUrl(UrlInterface::URL_TYPE_LINK);
    }

    /**
     * @param string $fromRequest
     *
     * @return bool
     */
    public function authoriseCode($fromRequest)
    {
        $fromConfig = $this->getConfigValue(ApsisConfigHelper::CONFIG_APSIS_ONE_ABANDONED_CARTS_PASSCODE);
        if ($fromRequest == $fromConfig) {
            return true;
        }

        return false;
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
}
