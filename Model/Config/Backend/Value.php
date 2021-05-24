<?php

namespace Apsis\One\Model\Config\Backend;

use Apsis\One\Model\Service\Config;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile as ProfileService;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Exception;

class Value extends ConfigValue
{
    /** Error flag  */
    const REGISTRY_NAME_FOR_ERROR = 'apsis_config_error';

    /** Retry check. 1 = success , 2 = error */
    const REGISTRY_NAME_FOR_RETRY = 'apsis_config_retry';

    const REGISTRY_NAME_FOR_OLD_VALUE = 'apsis_config_retry';

    const EVENT_DURATION_TO_TIMESTAMP_MAPPING = [
        Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_EVENTS_DURATION =>
            Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_DURATION_TIMESTAMP,
        Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_EVENTS_DURATION =>
            Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_DURATION_TIMESTAMP,
        Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_EVENTS_DURATION =>
            Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_DURATION_TIMESTAMP,
        Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_EVENTS_DURATION =>
            Config::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_DURATION_TIMESTAMP
    ];

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var WriterInterface
     */
    private $writer;

    /**
     * @var ProfileService
     */
    private $profileService;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ManagerInterface
     */
    private $messageManager;


    /**
     * Value constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ApsisCoreHelper $apsisLogHelper
     * @param ManagerInterface $messageManager
     * @param ProfileService $profileService
     * @param DateTime $dateTime
     * @param WriterInterface $writer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ApsisCoreHelper $apsisLogHelper,
        ManagerInterface $messageManager,
        ProfileService $profileService,
        DateTime $dateTime,
        WriterInterface $writer,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        $this->profileService = $profileService;
        $this->apsisCoreHelper = $apsisLogHelper;
        $this->dateTime = $dateTime;
        $this->writer = $writer;
        $this->messageManager = $messageManager;
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection
        );
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        //Find registry key, if exit don't save config
        if ($this->_registry->registry(self::REGISTRY_NAME_FOR_ERROR)) {
            $this->_dataSaveAllowed = false;
            return $this;
        }


        $isAccountEnabled = $this->apsisCoreHelper->isEnabled(
            $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->getScopeId()
        );
        $ignoreAccountEnableCheckConfigs = [
            Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED,
            Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ID,
            Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_REGION,
            Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN,
            Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE
        ];

        //If account is not enabled, do not save value.
        if (! $isAccountEnabled && ! in_array($this->getPath(), $ignoreAccountEnableCheckConfigs)) {
            //Set registry key
            $this->_registry->unregister(self::REGISTRY_NAME_FOR_ERROR);
            $this->_registry->register(self::REGISTRY_NAME_FOR_ERROR, true, true);

            $this->messageManager->addWarningMessage(
                "Unable to save config, account is not enabled. Reverted to default value"
            );
            $this->_dataSaveAllowed = false;
            return $this;
        }

        $fileUploadUrlCheckOnConfigs = [
            Config::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED,
            Config::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED.
            Config::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC,
            Config::CONFIG_APSIS_ONE_SYNC_SETTING_ADDITIONAL_TOPIC
        ];

        //If enabling Subscriber/Customer sync then validate file upload url reachable
        if (in_array($this->getPath(), $fileUploadUrlCheckOnConfigs) && ! $this->isFileUploadHostReachable()) {
            $this->_dataSaveAllowed = false;
            return $this;
        }

        //Set registry key
        $this->_registry->unregister(self::REGISTRY_NAME_FOR_OLD_VALUE);
        $this->_registry->register(
            self::REGISTRY_NAME_FOR_OLD_VALUE,
            $this->getOldValue()? $this->getOldValue() : $this->getValue(), true
        );

        return parent::beforeSave();
    }

    /**
     * @inheritdoc
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {

            //If section config and there exist an old value.
            if ($this->getPath() == Config::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION && $this->getOldValue()) {
                //Request full reset to Profile, Events and configs except api credentials and section mapping
                $this->profileService->fullResetRequest(__METHOD__, [Config::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION]);

                //Set registry key
                $this->_registry->unregister(self::REGISTRY_NAME_FOR_ERROR);
                $this->_registry->register(self::REGISTRY_NAME_FOR_ERROR, true, true);

                //Log it
                $this->apsisCoreHelper->log('User changed section.');
            }

            //If historical Event config.
            if (in_array($this->getPath(), array_keys(self::EVENT_DURATION_TO_TIMESTAMP_MAPPING))) {
                //If there is a value, create timestamp value.
                if ($this->getValue()) {
                    $dateTime = $this->dateTime->formatDate(true);

                    //Insert timestamp value for Event config
                    $this->writer->save(
                        self::EVENT_DURATION_TO_TIMESTAMP_MAPPING[$this->getPath()],
                        $dateTime,
                        $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                        $this->getScopeId()
                    );

                    //Log value change for timestamp Event config
                    $info = [
                        'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                        'Scope Id' => $this->getScopeId(),
                        'Config Path' => self::EVENT_DURATION_TO_TIMESTAMP_MAPPING[$this->getPath()],
                        'Old Value' => null,
                        'New Value' => $dateTime,
                    ];
                    $this->apsisCoreHelper->debug(__METHOD__, $info);
                } elseif (! (boolean) $this->getValue()) { //If there is no value, delete timestamp value.
                    $this->deleteDependantConfig();
                }
            }
        }

        //Log
        $info = [
            'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'Scope Id' => $this->getScopeId(),
            'Config Path' => $this->getPath(),
            'Old Value' => ($this->getOldValue() == $this->getValue()) ?
                $this->_registry->registry(self::REGISTRY_NAME_FOR_OLD_VALUE) : $this->getOldValue(),
            'New Value' => $this->getValue()
        ];
        $this->apsisCoreHelper->debug(__METHOD__, $info);

        return parent::afterSave();
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        //Delete dependant config for Event config
        if (in_array($this->getPath(), array_keys(self::EVENT_DURATION_TO_TIMESTAMP_MAPPING))) {
            $this->deleteDependantConfig();
        }

        //Log
        $info = [
            'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'Scope Id' => $this->getScopeId(),
            'Config Path' => $this->getPath(),
            'Old Value' => $this->getOldValue(),
            'New Value' => null
        ];
        $this->apsisCoreHelper->debug(__METHOD__, $info);

        return parent::afterDelete();
    }

    /**
     * Delete dependant configs
     */
    private function deleteDependantConfig()
    {
        try {
            $this->writer->delete(
                self::EVENT_DURATION_TO_TIMESTAMP_MAPPING[$this->getPath()],
                $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeId()
            );

            $info = [
                'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                'Scope Id' => $this->getScopeId(),
                'Config Path' => self::EVENT_DURATION_TO_TIMESTAMP_MAPPING[$this->getPath()],
                'Old Value' => $this->getOldValue(),
                'New Value' => null
            ];
            $this->apsisCoreHelper->debug(__METHOD__, $info);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @return bool
     */
    private function isFileUploadHostReachable()
    {
        // If 1 then already checked and is a success. Return true
        if ($this->_registry->registry(self::REGISTRY_NAME_FOR_RETRY) === 1) {
            return true;
        }

        // If 2 then already checked and is an error. Return false
        if ($this->_registry->registry(self::REGISTRY_NAME_FOR_RETRY) === 2) {
            return false;
        }

        $region = $this->apsisCoreHelper->getConfigValue(
            Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_REGION,
            $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->getScopeId()
        );

        if (empty($region)) {
            $msg = 'Region is not set in accounts. Please go to account section.';
            $this->apsisCoreHelper->log($msg);
            $this->messageManager->addWarningMessage(__($msg));

            //Set registry key, 2 = error
            $this->_registry->unregister(self::REGISTRY_NAME_FOR_RETRY);
            $this->_registry->register(self::REGISTRY_NAME_FOR_RETRY, 2, true);

            return false;
        }

        try {
            $this->apsisCoreHelper->validateIsUrlReachable($this->apsisCoreHelper->buildFileUploadHostName($region));

            //Set registry key, 1 = success and tried
            $this->_registry->unregister(self::REGISTRY_NAME_FOR_RETRY);
            $this->_registry->register(self::REGISTRY_NAME_FOR_RETRY, 1, true);

            return true;
        } catch (Exception $e) {
            //Log it
            $this->apsisCoreHelper->logError(__METHOD__, $e);

            //Add to message
            $msg = '. Profile sync is disabled and will not work until host for URL is whitelisted.';
            $this->messageManager->addWarningMessage(__($e->getMessage() . $msg));

            //Set registry key, 2 = error
            $this->_registry->unregister(self::REGISTRY_NAME_FOR_RETRY);
            $this->_registry->register(self::REGISTRY_NAME_FOR_RETRY, 2, true);

            return false;
        }
    }
}
