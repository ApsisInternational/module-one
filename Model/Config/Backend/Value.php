<?php

namespace Apsis\One\Model\Config\Backend;

use Apsis\One\Model\Service\Config;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile as ProfileService;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Apsis\One\Model\ResourceModel\Event;
use Exception;
use Apsis\One\Model\Event as EventModel;

class Value extends ConfigValue
{
    const REGISTRY_NAME_FOR_STATUS_CHECK = 'apsis_config_status_check';
    const REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK = 'apsis_config_host_reachable_check';
    const REGISTRY_NAME_FOR_OLD_VALUE = 'apsis_config_old_value';

    const EVENT_HISTORICAL_DURATION = [
        Config::EVENTS_HISTORICAL_ORDER_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER,
        Config::EVENTS_HISTORICAL_CART_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART,
        Config::EVENTS_HISTORICAL_REVIEW_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW,
        Config::EVENTS_HISTORICAL_WISHLIST_EVENTS_DURATION => EventModel::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST
    ];

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
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Event
     */
    private $eventResource;

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
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        $this->eventResource = $eventResource;
        $this->profileService = $profileService;
        $this->apsisCoreHelper = $apsisLogHelper;
        $this->messageManager = $messageManager;
        $this->request = $request;
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
        //Account config
        if (in_array($this->getPath(), Config::CONFIG_PATHS_ACCOUNT)) {

            //Already validated and success. Let the value be saved in DB
            if ($this->assertRegistryStatus(1)) {
                return parent::beforeSave();
            }

            //No need for validation, observer will remove token config for this context
            $groups = $this->request->getPost('groups');
            if (isset($groups['oauth']['fields']['id']['inherit']) ||
                isset($groups['oauth']['fields']['secret']['inherit'])
            ) {
                return parent::beforeSave();
            }
        }

        //Already validated and failure. Do not let the value saved in DB
        if ($this->assertRegistryStatus(2)) {
            $this->_dataSaveAllowed = false;
            return $this;
        }

        //Validate account enabled for all config other then account config. If not enabled, do not save value in DB
        $isAccountEnabled = $this->apsisCoreHelper
            ->isEnabled($this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $this->getScopeId());
        if (! $isAccountEnabled && ! in_array($this->getPath(), Config::CONFIG_PATHS_ACCOUNT)) {
            $this->setRegistryStatus(2);
            $this->messageManager
                ->addWarningMessage("Cant save config, account is not enabled. Reverted to default value");
            $this->_dataSaveAllowed = false;
            return $this;
        }

        //Event sync config, validate host reachable for file upload api endpoint. If false, do not save value in DB
        if (in_array($this->getPath(), Config::CONFIG_PATHS_SYNCS) && ! $this->isFileUploadHostReachable()) {
            $this->_dataSaveAllowed = false;
            return $this;
        }

        //Save old config in registry to be used later on in logs
        $this->_registry->unregister(self::REGISTRY_NAME_FOR_OLD_VALUE);
        $this->_registry->register(
            self::REGISTRY_NAME_FOR_OLD_VALUE,
            $this->getOldValue()? $this->getOldValue() : $this->getValue(), true
        );

        //At this point, it means to let model save value in DB
        return parent::beforeSave();
    }

    /**
     * @inheritdoc
     */
    public function afterSave()
    {
        //Value changed, determined by the model
        if ($this->isValueChanged()) {

            //Account config
            $check = $this->isAccountConfigThenEvaluate();
            if ($check === true) {
                return parent::afterSave();
            } elseif ($check === false) {
                return $this;
            }

            //Section config
            $this->isSectionConfigThenEvaluate();

            //Event historical config
            $this->isEventHistoricalConfigThenEvaluate();

            //Log value change
            $this->log(__METHOD__);
        }

        //At this point, it means to let model save value in DB
        return parent::afterSave();
    }

    /**
     * Process function for changing status of historical pending to pending for inclusion in the sync process.
     */
    private function isEventHistoricalConfigThenEvaluate()
    {
        if (in_array($this->getPath(), array_keys(self::EVENT_HISTORICAL_DURATION)) &&
            $this->getValue() && ! empty($storeIdArr = $this->apsisCoreHelper->getAllStoreIds())
        ) {
            $status = $this->eventResource->setPendingStatusOnHistoricalPendingEvents(
                $this->apsisCoreHelper,
                $this->getValue(),
                self::EVENT_HISTORICAL_DURATION[$this->getPath()],
                $storeIdArr
            );
            if ($status) {
                $info = [
                    'Message' => "Updated $status historical events to sync.",
                    'Event type' => self::EVENT_HISTORICAL_DURATION[$this->getPath()],
                    'Store Ids' => implode(', ', $storeIdArr)
                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);
            }
        }
    }

    /**
     * Partial reset, reset all configs other then account, as well as events and profiles
     */
    private function isSectionConfigThenEvaluate()
    {
        if ($this->getPath() == Config::MAPPINGS_SECTION_SECTION && $this->getOldValue()) {
            $this->profileService->resetRequest(
                'Partial reset from section mapping change',
                [Config::MAPPINGS_SECTION_SECTION]
            );
            $this->setRegistryStatus(2);
            $this->apsisCoreHelper->log('User changed section.');
        }
    }

    /**
     * @return bool|null
     */
    private function isAccountConfigThenEvaluate()
    {
        if (in_array($this->getPath(), Config::CONFIG_PATHS_ACCOUNT)) {

            //Already validated and success. No need to do it again.
            if ($this->assertRegistryStatus(1, __METHOD__)) {
                return true;
            }

            //Always Remove old config, reset Profiles and Events
            $this->profileService->resetRequest('Full reset from API credentials change');

            //No need for validation, observer will remove token config for this context
            $groups = $this->request->getPost('groups');
            if (isset($groups['oauth']['fields']['id']['inherit']) ||
                isset($groups['oauth']['fields']['secret']['inherit'])
            ) {
                $this->log(__METHOD__);
                return true;
            }

            //Obtained all required values to validate api
            $id = $groups['oauth']['fields']['id']['value'] ?? false;
            $secret = $this->getSecretValue($groups);
            $region = $groups['oauth']['fields']['region']['value'] ?? false;

            //It's a fail if anyone of these are empty
            if (empty($id) || empty($secret) || empty($region)) {
                $this->setRegistryStatus(2);
                return false;
            }

            //Get scope, if none found then it is default
            $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

            //Determine success or fail
            $msg = $this->apsisCoreHelper->isApiCredentialsValid($id, $secret, $region, $scope, $this->getScopeId());
            if ($msg === EncryptedValue::SUCCESS_MESSAGE) {
                $this->apsisCoreHelper->log(EncryptedValue::SUCCESS_MESSAGE);
                $this->messageManager->addSuccessMessage(__(EncryptedValue::SUCCESS_MESSAGE));
                $this->setRegistryStatus(1);
                return true;
            } else {
                $this->messageManager->addWarningMessage(__($msg));
                $this->setRegistryStatus(2);
                return false;
            }
        }
        return null;
    }

    /**
     * @param array $groups
     *
     * @return false|string
     */
    private function getSecretValue(array $groups)
    {
        $secret = (string) $groups['oauth']['fields']['secret']['value'] ?? false;
        if (empty($secret)) {
            return false;
        }

        if (! preg_match('/^\*+$/', $secret)) {
            if (empty($secret)) {
                return false;
            }
            return $secret;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
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
     * @return bool
     */
    private function isFileUploadHostReachable()
    {
        // If 1 then already checked and is a success. Return true
        if ($this->_registry->registry(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK) === 1) {
            return true;
        }

        // If 2 then already checked and is an error. Return false
        if ($this->_registry->registry(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK) === 2) {
            return false;
        }

        $region = $this->apsisCoreHelper->getConfigValue(
            Config::ACCOUNTS_OAUTH_REGION,
            $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->getScopeId()
        );

        if (empty($region)) {
            $msg = 'Region is not set in accounts. Please go to account section.';
            $this->apsisCoreHelper->log($msg);
            $this->messageManager->addWarningMessage(__($msg));

            //Set registry key, 2 = error
            $this->_registry->unregister(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK);
            $this->_registry->register(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK, 2, true);

            return false;
        }

        try {
            $this->apsisCoreHelper->validateIsUrlReachable($this->apsisCoreHelper->buildFileUploadHostName($region));

            //Set registry key, 1 = success and tried
            $this->_registry->unregister(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK);
            $this->_registry->register(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK, 1, true);

            return true;
        } catch (Exception $e) {
            //Log it
            $this->apsisCoreHelper->logError(__METHOD__, $e);

            //Add to message
            $msg = '. Profile sync is disabled and will not work until host for URL is whitelisted.';
            $this->messageManager->addWarningMessage(__($e->getMessage() . $msg));

            //Set registry key, 2 = error
            $this->_registry->unregister(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK);
            $this->_registry->register(self::REGISTRY_NAME_FOR_HOST_REACHABLE_CHECK, 2, true);

            return false;
        }
    }

    /**
     * @param int $check
     * @param string $method
     *
     * @return bool
     */
    public function assertRegistryStatus(int $check, string $method = '')
    {
        $assert = $this->_registry->registry(Value::REGISTRY_NAME_FOR_STATUS_CHECK) === $check;
        if ($assert && strlen($method)) {
            $this->log($method);
        }
        return $assert;
    }

    /**
     * @param int $value
     */
    private function setRegistryStatus(int $value)
    {
        $this->_registry->unregister(Value::REGISTRY_NAME_FOR_STATUS_CHECK);
        $this->_registry->register(Value::REGISTRY_NAME_FOR_STATUS_CHECK, $value, true);
    }

    /**
     * @param string $method
     */
    private function log(string $method)
    {
        $info = [
            'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'Scope Id' => $this->getScopeId(),
            'Config Path' => $this->getPath(),
            'Old Value' => ($this->getOldValue() == $this->getValue()) ?
                $this->_registry->registry(self::REGISTRY_NAME_FOR_OLD_VALUE) : $this->getOldValue(),
            'New Value' => $this->getValue()
        ];
        $this->apsisCoreHelper->debug($method, $info);
    }
}
