<?php

namespace Apsis\One\Model\Config\Backend;

use Apsis\One\Model\Event as EventModel;
use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\Service\Config;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile as ProfileService;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Data\ProcessorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Throwable;

class Value extends ConfigValue implements ProcessorInterface
{
    const REGISTRY_NAME_FOR_STATUS_CHECK = 'apsis_config_status_check';
    const REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK = 'apsis_config_host_reachable_check';
    const REGISTRY_NAME_FOR_OLD_VALUE = 'apsis_config_old_value';

    const SUCCESS = 1;
    const FAIL = 2;

    const MSG_NOT_ENABLE_ACCOUNT = 'Cant save configurations, account is not enabled. Reverted to default value';
    const MSG_SECTION_RESET = 'Partial reset from section mapping change';
    const MSG_SECTION_CHANGE = 'User changed section';
    const MSG_SUCCESS_ACCOUNT = 'API credentials are valid and Magento KeySpace exist';
    const MSG_FAIL_HOST_FILE_UPLOAD = 'Profile sync is disabled and will not work until host for URL is whitelisted';
    const MSG_FAIL_NO_REGION = 'Region is not set in accounts. Please go to account section';
    const MSG_ACCOUNT_CHANGE = 'User changed API credentials. Sending full reset request.';
    const MSG_ACCOUNT_RESET = 'Full reset from API credentials change';
    const MSG_ACCOUNT_INHERIT = 'Config set to inherit value. Passed to observer to handle change request';
    const MSG_ACCOUNT_MISSING = 'Not all configs exist for validation';
    const MSG_UPDATED = "Updated %d historical events to sync";

    const EVENT_HISTORICAL_DURATION = [
        Config::EVENTS_HISTORICAL_ORDER_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
        Config::EVENTS_HISTORICAL_CART_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
        Config::EVENTS_HISTORICAL_REVIEW_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
        Config::EVENTS_HISTORICAL_WISHLIST_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST
    ];

    /**
     * @var ProfileService
     */
    private ProfileService $profileService;

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var Event
     */
    private Event $eventResource;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

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
     * @param RequestInterface $request
     * @param Event $eventResource
     * @param EncryptorInterface $encryptor
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
        RequestInterface $request,
        Event $eventResource,
        EncryptorInterface $encryptor,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        $this->encryptor = $encryptor;
        $this->eventResource = $eventResource;
        $this->profileService = $profileService;
        $this->apsisCoreHelper = $apsisLogHelper;
        $this->messageManager = $messageManager;
        $this->request = $request;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection);
    }

    /**
     * @inheritdoc
     */
    public function __sleep()
    {
        $properties = parent::__sleep();

        //If secret config
        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
            return array_diff($properties, ['_encryptor']);
        }

        //All other configs
        return $properties;
    }

    /**
     * @inheritdoc
     */
    public function __wakeup()
    {
        parent::__wakeup();

        //If secret config
        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
            $this->encryptor = ObjectManager::getInstance()->get(EncryptorInterface::class);
        }
    }

    /**
     * @return ConfigValue|void
     */
    protected function _afterLoad()
    {
        //If secret config
        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
            if (strlen($decrypted = (string) $this->processValue((string) $this->getValue()))) {
                $this->setValue($decrypted);
            }

            return;
        }

        //All other configs
        return parent::_afterLoad();
    }

    /**
     * @inheritdoc
     */
    public function processValue($value): string
    {
        //If secret config
        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET && ! empty($value)) {
            return $this->encryptor->decrypt($value);
        }

        return $value;
    }

    /**
     * @return int
     */
    private function isAccountConfigThenAssertBeforeSave(): int
    {
        //Account config
        if (in_array($this->getPath(), Config::CONFIG_PATHS_ACCOUNT)) {
            $groups = $this->request->getPost('groups');

            //Already validated and success. Let the value be saved in DB
            //Assert inherit configs. Save value in DB, Observer will remove token configs for this context
            if ($this->assertRegistryStatus(self::SUCCESS) || $this->apsisCoreHelper->isInheritConfig($groups)) {
                //If secret config
                if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
                    // Success and need to run beforeSave for secret config only
                    return 1;
                } else { //All other account configs
                    // Save old value to log in afterSave in case old value does not exist in afterSave
                    $this->registerOldValue();

                    // Success and need to let parent process config
                    return 2;
                }
            } elseif ($this->assertRegistryStatus(self::FAIL)) {
                //Already validated and failed. Do not let the value saved in DB
                $this->_dataSaveAllowed = false;

                //If secret config
                if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
                    // Failure from secret config, return void
                    return 3;
                } else { //All other account configs
                    // Failure from other account configs then return $this
                    return 4;
                }
            }
        }

        //Nothing
        return 0;
    }

    /**
     * @return bool
     */
    private function isConfigOtherThenAccountThenAssertBeforeSave(): bool
    {
        //If failed flag exist then do not let save config in DB
        if ($this->assertRegistryStatus(self::FAIL)) {
            $this->_dataSaveAllowed = false;
            return false;
        }

        $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $isAccountEnabled = $this->apsisCoreHelper->isEnabled($scope, $this->getScopeId());

        //Validate account enabled for all config other then account config.
        if (! $isAccountEnabled && ! in_array($this->getPath(), Config::CONFIG_PATHS_ACCOUNT)) {
            //If not enabled, do not save value in DB
            $this->_dataSaveAllowed = false;
            $this->setRegistryStatus(self::FAIL);

            //Message for admin user
            $this->messageManager->addWarningMessage(self::MSG_NOT_ENABLE_ACCOUNT);

            return false;
        }

        //Event sync config, validate host reachable for file upload api endpoint.
        if (in_array($this->getPath(), Config::CONFIG_PATHS_SYNCS) && ! $this->isFileUploadHostReachable()) {
            //If false, do not save value in DB
            $this->_dataSaveAllowed = false;

            return false;
        }

        // Save old value to log in afterSave in case old value does not exist in afterSave
        $this->registerOldValue();

        //All good at this point
        return true;
    }

    /**
     * @return $this|void
     */
    public function beforeSave()
    {
        //Account config assertions
        $check = $this->isAccountConfigThenAssertBeforeSave();
        if ($check === 1) { // Success and need to run beforeSave for secret config only
            //Save secret account config
            $this->accountSecretBeforeSave();
            return;
        } elseif ($check === 2) { // Success and need to let parent process config
            //Save all other account configs
            return parent::beforeSave();
        } elseif ($check === 3) { // Failure from secret config, return void
            return;
        } elseif ($check === 4) { // Failure from other account configs, return $this
            return $this;
        }

        //All configs other than account
        $check = $this->isConfigOtherThenAccountThenAssertBeforeSave();
        if (! $check) {
            return $this;
        }

        //At this point, it means to let model save value in DB
        return parent::beforeSave();
    }

    /**
     * @return ConfigValue|$this
     */
    public function afterSave()
    {
        //Flag exist
        if ($this->assertRegistryStatus(self::FAIL)) {
            return $this;
        }

        //Value changed, determined by the model
        if ($this->isValueChanged()) {
            //Account configs
            $this->isAccountConfigThenEvaluateAfterSave();

            //Section config
            $this->isSectionConfigThenEvaluateAfterSave();

            //Event historical config
            $this->isEventHistoricalConfigThenEvaluateAfterSave();

            //Log value change
            $this->log(__METHOD__);
        }

        return parent::afterSave();
    }

    /**
     * @return ConfigValue
     */
    public function afterDelete()
    {
        $this->log(__METHOD__);
        return parent::afterDelete();
    }

    /**
     * Specific just for secret value
     *
     * @return void
     */
    public function accountSecretBeforeSave(): void
    {
        $this->_dataSaveAllowed = false;

        $encrypted = $this->getValueForSecretConfig((string) $this->getValue());
        if ($encrypted !== null) {
            $this->_dataSaveAllowed = true;

            if (strlen($encrypted)) {
                $this->setValue($encrypted);
            }
        }
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    private function getValueForSecretConfig(string $value)
    {
        // don't save value, if an obscured value was received.
        //This indicates that data was not changed.
        if (! preg_match('/^\*+$/', $value) && ! empty($value)) {
            return $this->encryptor->encrypt($value);
        } elseif (empty($value)) {
            return '';
        }

        return null;
    }

    /**
     * @param array $groups
     *
     * @return string
     */
    private function getSecretValue(array $groups): string
    {
        $fromDb = false;

        //If secret config
        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
            $result = $this->getValueForSecretConfig($this->getValue());

            if (is_string($result) && strlen($result)) {
                return $this->processValue($result);
            } elseif ($result === null) {
                $fromDb = true;
            }
        } elseif (isset($groups['oauth']['fields']['secret']['value'])) {
            $secret = $groups['oauth']['fields']['secret']['value'];
            $result = $this->getValueForSecretConfig($secret);

            if (is_string($result) && strlen($result)) {
                return $this->processValue($secret);
            } elseif ($result === null) {
                $fromDb = true;
            }
        }

        if ($fromDb) {
            return (string) $this->apsisCoreHelper->getClientSecret(
                $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeId()
            );
        }

        return '';
    }

    /**
     * Save old config in registry to be used later on in logs
     *
     * @return void
     */
    private function registerOldValue(): void
    {
        //If secret config, do not log it. Log only a placeholder text.
        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
            $oldValue = 'Encrypted value';
        } else {
            $oldValue = $this->getOldValue() ? $this->getOldValue() : $this->getValue();
        }

        $this->_registry->unregister(self::REGISTRY_NAME_FOR_OLD_VALUE);
        $this->_registry->register(self::REGISTRY_NAME_FOR_OLD_VALUE, $oldValue, true);
    }

    /**
     * Process function for changing status of historical pending events.
     * Change to pending status for inclusion in the sync process.
     *
     * @return void
     */
    private function isEventHistoricalConfigThenEvaluateAfterSave(): void
    {
        $historyConfigs = array_keys(self::EVENT_HISTORICAL_DURATION);
        $storeIdArr = $this->apsisCoreHelper->getStoreIdsBasedOnScope();

        if (in_array($this->getPath(), $historyConfigs) && $this->getValue() && ! empty($storeIdArr)) {
            $status = $this->eventResource->setPendingStatusOnHistoricalPendingEvents(
                $this->apsisCoreHelper,
                $this->getValue(),
                self::EVENT_HISTORICAL_DURATION[$this->getPath()],
                $storeIdArr
            );

            if ($status) {
                $info = [
                    'Message' => sprintf(self::MSG_UPDATED, $status),
                    'Event type' => self::EVENT_HISTORICAL_DURATION[$this->getPath()],
                    'Store Ids' => implode(', ', $storeIdArr)
                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);
            }
        }
    }

    /**
     * Partial reset, reset all configs other then account, as well as events and profiles
     *
     * @return void
     */
    private function isSectionConfigThenEvaluateAfterSave(): void
    {
        if ($this->getPath() == Config::MAPPINGS_SECTION_SECTION) {
            // If section was mapped before
            if ($this->getOldValue()) {
                // At this point, section value has changed. Send partial reset request
                $this->apsisCoreHelper->log(self::MSG_SECTION_CHANGE);
                $this->profileService->resetRequest(self::MSG_SECTION_RESET, [Config::MAPPINGS_SECTION_SECTION]);

                //Set this key so other configs (will get default values) from same page do not get save.
                $this->setRegistryStatus(self::FAIL);
            }
        }
    }

    /**
     * @return void
     */
    private function isAccountConfigThenEvaluateAfterSave(): void
    {
        if (in_array($this->getPath(), Config::CONFIG_PATHS_ACCOUNT)) {
            $groups = $this->request->getPost('groups');

            //No need for validation, observer will remove token config for this context
            if ($this->apsisCoreHelper->isInheritConfig($groups)) {
                $this->log(self::MSG_ACCOUNT_INHERIT);
                return;
            }

            //Already validated and success. No need to do it again.
            if ($this->assertRegistryStatus(self::SUCCESS)) {
                return;
            }

            $secret = $this->getSecretValue($groups);

            // Enabled account config
            if ($this->getPath() == Config::ACCOUNTS_OAUTH_ENABLED) {
                //If account is being disabled
                if ($this->getOldValue() && empty($this->getValue())) {
                    $this->setRegistryStatus(self::SUCCESS);
                    return;
                }
            } else {
                $old = $this->getOldValue();
                $new = $this->getValue();

                // If config is secret
                if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
                    $old = $this->processValue($old);
                    $new = $secret;
                }

                //If already exist an old value and not same as new value. log it and perform full reset
                if ($old && $new && $old != $new) {
                    $this->apsisCoreHelper->log(self::MSG_ACCOUNT_CHANGE);
                    $this->profileService->resetRequest(self::MSG_ACCOUNT_RESET);
                }
            }

            //It's a fail if anyone of these are empty
            if (! $this->isCompulsoryConfigExistForValidation($groups) || empty($secret)) {
                $this->messageManager->addWarningMessage(__(self::MSG_ACCOUNT_MISSING));
                $this->apsisCoreHelper->log(self::MSG_ACCOUNT_MISSING);
                $this->setRegistryStatus(self::FAIL);

                return;
            }

            $id = $groups['oauth']['fields']['id']['value'];
            $region = $groups['oauth']['fields']['region']['value'];
            $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

            $msg = $this->apsisCoreHelper->isApiCredentialsValid($id, $secret, $region, $scope, $this->getScopeId());

            //Determine success or fail
            if ($msg === self::MSG_SUCCESS_ACCOUNT) {
                $this->messageManager->addSuccessMessage(__(self::MSG_SUCCESS_ACCOUNT));
                $this->log(self::MSG_SUCCESS_ACCOUNT);
                $this->setRegistryStatus(self::SUCCESS);
            } else {
                $this->messageManager->addWarningMessage(__($msg));
                $this->setRegistryStatus(self::FAIL);
            }
        }
    }

    /**
     * @return bool
     */
    private function isFileUploadHostReachable(): bool
    {
        // If 1 then already checked and is a success. Return true
        if ($this->assertRegistryReachableCheck(self::SUCCESS)) {
            return true;
        }

        // If 2 then already checked and is an error. Return false
        if ($this->assertRegistryReachableCheck(self::FAIL)) {
            return false;
        }

        $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $region = $this->apsisCoreHelper->getConfigValue(Config::ACCOUNTS_OAUTH_REGION, $scope, $this->getScopeId());

        if (empty($region)) {
            $this->apsisCoreHelper->log(self::MSG_FAIL_NO_REGION);
            $this->messageManager->addWarningMessage(__(self::MSG_FAIL_NO_REGION));

            $this->setRegistryReachableCheck(self::FAIL);

            return false;
        }

        try {
            $this->apsisCoreHelper->validateIsUrlReachable($this->apsisCoreHelper->buildFileUploadHostName($region));

            //Set registry key, 1 = success and tried
            $this->setRegistryReachableCheck(self::SUCCESS);

            return true;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addWarningMessage(__($e->getMessage() . ' .' . self::MSG_FAIL_HOST_FILE_UPLOAD));

            $this->setRegistryReachableCheck(self::FAIL);

            return false;
        }
    }

    /**
     * @param int $check
     *
     * @return bool
     */
    public function assertRegistryReachableCheck(int $check): bool
    {
        return $this->_registry->registry(self::REGISTRY_NAME_FOR_STATUS_CHECK) === $check;
    }

    /**
     * @param int $value
     *
     * @return void
     */
    private function setRegistryReachableCheck(int $value): void
    {
        $this->_registry->unregister(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK);
        $this->_registry->register(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK, $value, true);
    }

    /**
     * @param int $check
     *
     * @return bool
     */
    public function assertRegistryStatus(int $check): bool
    {
        return $this->_registry->registry(self::REGISTRY_NAME_FOR_STATUS_CHECK) === $check;
    }

    /**
     * @param int $value
     *
     * @return void
     */
    private function setRegistryStatus(int $value): void
    {
        $this->_registry->unregister(self::REGISTRY_NAME_FOR_STATUS_CHECK);
        $this->_registry->register(self::REGISTRY_NAME_FOR_STATUS_CHECK, $value, true);
    }

    /**
     * @param string $msg
     *
     * @return void
     */
    private function log(string $msg): void
    {
        $oldValue = ($this->getOldValue() == $this->getValue()) ?
            $this->_registry->registry(self::REGISTRY_NAME_FOR_OLD_VALUE) : $this->getOldValue();
        $newValue = $this->getValue();

        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {
            $oldValue = $newValue = 'Encrypted value';
        }

        $info = [
            'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'Scope Id' => $this->getScopeId(),
            'Config Path' => $this->getPath(),
            'Old Value' => $oldValue,
            'New Value' => $newValue
        ];
        $this->apsisCoreHelper->debug($msg, $info);
    }

    /**
     * @param array $groups
     *
     * @return bool
     */
    private function isCompulsoryConfigExistForValidation(array $groups): bool
    {
        return isset($groups['oauth']['fields']['id']['value']) &&
            isset($groups['oauth']['fields']['region']['value']) &&
            ! empty($groups['oauth']['fields']['id']['value']) &&
            ! empty($groups['oauth']['fields']['region']['value']);
    }
}
