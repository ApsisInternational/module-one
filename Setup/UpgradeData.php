<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Config\Source\System\Region;
use Apsis\One\Model\Event;
use Apsis\One\Model\Events\Historical;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Registry;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class UpgradeData implements UpgradeDataInterface
{
    const PRE_220_HISTORICAL_EVENT_DONE_CONFIGS = [
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART => 'apsis_one_events/events/quote_history_done_flag',
        Event::EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER => 'apsis_one_events/events/order_history_done_flag',
        Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW => 'apsis_one_events/events/review_history_done_flag',
        Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST => 'apsis_one_events/events/wishlist_history_done_flag'
    ];
    const PRE_220_HISTORICAL_EVENT_TIMESTAMPS = [
        'apsis_one_events/events/cart_event_duration_timestamp',
        'apsis_one_events/events/order_event_duration_timestamp',
        'apsis_one_events/events/review_event_duration_timestamp',
        'apsis_one_events/events/wishlist_event_duration_timestamp'
    ];
    const PRE_220_REDUNDANT_CRON_JOB = 'apsis_one_find_historical_events';

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Random
     */
    private $random;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var RoleFactory
     */
    private $roleFactory;

    /**
     * @var RulesFactory
     */
    private $rulesFactory;

    /**
     * @var Historical
     */
    private $historicalEvents;

    /**
     * @var State
     */
    private $appState;

    /**
     * UpgradeData constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Random $random
     * @param EncryptorInterface $encryptor
     * @param Registry $registry
     * @param ProfileResource $profileResource
     * @param EventResource $eventResource
     * @param RoleFactory $roleFactory
     * @param RulesFactory $rulesFactory
     * @param Historical $historicalEvents
     * @param State $appState
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Random $random,
        EncryptorInterface $encryptor,
        Registry $registry,
        ProfileResource $profileResource,
        EventResource $eventResource,
        RoleFactory $roleFactory,
        RulesFactory $rulesFactory,
        Historical $historicalEvents,
        State $appState
    ) {
        $this->appState = $appState;
        $this->historicalEvents = $historicalEvents;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->random = $random;
        $this->encryptor = $encryptor;
        $this->registry = $registry;
        $this->profileResource = $profileResource;
        $this->eventResource = $eventResource;
        $this->roleFactory = $roleFactory;
        $this->rulesFactory = $rulesFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        try {
            $setup->startSetup();

            if (version_compare($context->getVersion(), '1.2.0', '<')) {
                $this->upgradeOneTwoZero($setup);
            }
            if (version_compare($context->getVersion(), '1.5.0', '<')) {
                $this->upgradeOneFiveZero($setup);
            }
            if (version_compare($context->getVersion(), '1.9.0', '<')) {
                $this->upgradeOneNineZero($setup);
            }
            if (version_compare($context->getVersion(), '1.9.4', '<')) {
                $this->upgradeOneNineFour($setup);
            }
            if (version_compare($context->getVersion(), '1.9.5', '<')) {
                $this->upgradeOneNineFive($setup);
            }
            if (version_compare($context->getVersion(), '2.0.0', '<')) {
                $this->upgradeTwoZeroZero($setup);
            }
            if (version_compare($context->getVersion(), '2.0.7', '<')) {
                $this->upgradeTwoZeroSeven($setup);
            }
        } catch (Throwable $e) {
            $setup->endSetup();
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeTwoZeroSeven(ModuleDataSetupInterface $setup)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);

            $configs = [ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC, ApsisConfigHelper::SYNC_SETTING_ADDITIONAL_TOPIC];
            foreach ($this->apsisCoreHelper->getStores(true) as $store) {
                foreach ($configs as $config) {
                    $value = [];
                    $topics = explode(',', (string) $store->getConfig($config));
                    foreach ($topics as $topicStr) {
                        $topicArr = explode('|', (string) $topicStr);
                        if (empty($topicArr) || count($topicArr) !== 4) {
                            continue;
                        }

                        $value[] = $topicArr[1] . '|' . $topicArr[3];
                    }

                    $scopeArray = $this->apsisCoreHelper->resolveContext(
                        ScopeInterface::SCOPE_STORES, $store->getId(), $config
                    );
                    $this->apsisCoreHelper->saveConfigValue(
                        $config, implode(',', $value), $scopeArray['scope'], $scopeArray['id']
                    );
                    $store->resetConfig();
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeTwoZeroZero(ModuleDataSetupInterface $setup)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);

            //Set status to 5 for each Profile type (for all Profiles) if given Profile type has is_[PROFILE_TYPE] = 0
            $this->profileResource->resetProfiles(
                $this->apsisCoreHelper,
                [],
                [],
                Profile::SYNC_STATUS_NA,
                ['condition' => 'is_', 'value' => Profile::NO_FLAG]
            );

            //Remove all ui bookmarks belonging to module to force rebuild new ui bookmarks
            $grids = ['apsis_abandoned_grid', 'apsis_event_grid', 'apsis_profile_grid'];
            $setup->getConnection()->delete(
                $setup->getTable('ui_bookmark'),
                $setup->getConnection()->quoteInto('namespace in (?)', $grids)
            );

            //Fetch historical events
            $this->historicalEvents->process($this->apsisCoreHelper);

            //Remove redundant configs
            $configs = array_merge(
                array_values(self::PRE_220_HISTORICAL_EVENT_DONE_CONFIGS),
                self::PRE_220_HISTORICAL_EVENT_TIMESTAMPS
            );
            foreach ($configs as $config) {
                $setup->getConnection()->delete(
                    $setup->getTable('core_config_data'),
                    $setup->getConnection()->quoteInto('path = ?', $config)
                );
            }
            $info = ['Removed configs.' => array_values($configs)];
            $this->apsisCoreHelper->debug(__METHOD__, $info);

            //Removed redundant cron job.
            $setup->getConnection()->delete(
                $setup->getTable('cron_schedule'),
                $setup->getConnection()->quoteInto('job_code = ?', self::PRE_220_REDUNDANT_CRON_JOB)
            );
            $info = ['Removed redundant cron job if existed.' => self::PRE_220_REDUNDANT_CRON_JOB];
            $this->apsisCoreHelper->debug(__METHOD__, $info);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneNineFive(ModuleDataSetupInterface $setup)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);

            //Remove both token and token expiry for force regeneration of token
            $configs = [
                ApsisConfigHelper::ACCOUNTS_OAUTH_TOKEN,
                ApsisConfigHelper::ACCOUNTS_OAUTH_TOKEN_EXPIRE
            ];
            foreach ($configs as $config) {
                $setup->getConnection()->delete(
                    $setup->getTable('core_config_data'),
                    $setup->getConnection()->quoteInto('path = ?', $config)
                );
            }

            //Reset all profiles to re-sync if it has failed sync status
            $this->profileResource->resetProfiles(
                $this->apsisCoreHelper,
                [],
                [],
                Profile::SYNC_STATUS_PENDING,
                ['condition' => '_sync_status', 'value' => Profile::SYNC_STATUS_FAILED]
            );

            //Reset all events to re-sync if it has failed sync status
            $whereE = $setup->getConnection()->quoteInto('status = ?', Profile::SYNC_STATUS_FAILED);
            $this->eventResource->resetEvents($this->apsisCoreHelper, [], [], [$whereE]);

            //Create Role for APSIS Support
            $role = $this->roleFactory->create()
                ->setRoleName('APSIS Support Agent')
                ->setUserType(UserContextInterface::USER_TYPE_ADMIN)
                ->setUserId(0)
                ->setRoleType(RoleGroup::ROLE_TYPE)
                ->setSortOrder(0)
                ->setTreeLevel(1)
                ->setParentId(0)
                ->save();

            $resource = [
                'Apsis_One::reports',
                'Apsis_One::profile',
                'Apsis_One::event',
                'Apsis_One::abandoned',
                'Apsis_One::logviewer',
                'Apsis_One::config',
            ];

            $this->rulesFactory->create()
                ->setRoleId($role->getId())
                ->setResources($resource)
                ->saveRel();

            $this->apsisCoreHelper->log('User Role created: "APSIS Support Agent"');
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneNineFour(ModuleDataSetupInterface $setup)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);
            if ($this->registry->registry(UpgradeSchema::REGISTRY_NAME)) {
                $this->profileResource->updateSubscriberStoreId(
                    $setup->getConnection(),
                    $setup->getTable('newsletter_subscriber'),
                    $setup->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                    $this->apsisCoreHelper
                );
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneNineZero(ModuleDataSetupInterface $setup)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);
            foreach ($this->apsisCoreHelper->getStores(true) as $store) {
                $oldValue = (string) $store->getConfig(ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC);
                if (strlen($oldValue) && ! empty($topics = explode(',', $oldValue)) && count($topics)) {
                    $scopeArray = $this->apsisCoreHelper->resolveContext(
                        ScopeInterface::SCOPE_STORES,
                        $store->getId(),
                        ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC
                    );
                    $this->apsisCoreHelper->saveConfigValue(
                        ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC,
                        $topics[0],
                        $scopeArray['scope'],
                        $scopeArray['id']
                    );
                }
                $store->resetConfig();
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneFiveZero(ModuleDataSetupInterface $setup)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);

            //Take value from older path
            $oldConfigPath = 'apsis_one_sync/sync/endpoint_key';
            $oldValue = (string) $this->apsisCoreHelper->getConfigValue(
                $oldConfigPath,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
            if (strlen($oldValue)) {
                $value = $oldValue;
                //Remove old path
                $this->apsisCoreHelper->deleteConfigByScope(
                    $oldConfigPath,
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
            } else {
                $value = (string) $this->apsisCoreHelper->getConfigValue(
                    ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY,
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
            }
            if (strlen($value)) {
                //Encrypt and save in new path
                $this->apsisCoreHelper->saveConfigValue(
                    ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY,
                    $this->encryptor->encrypt($value),
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneTwoZero(ModuleDataSetupInterface $setup)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);
            $this->generateGlobalKey();
            foreach ($this->apsisCoreHelper->getStores(true) as $store) {
                $topics = (string) $store->getConfig(ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC);
                $scopeArray = $this->apsisCoreHelper->resolveContext(
                    ScopeInterface::SCOPE_STORES,
                    $store->getId(),
                    ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC
                );

                if (strlen($topics)) {
                    $this->updateConsentListTopicData($topics, $scopeArray);
                    $this->updateConsentForProfiles($setup, $topics);
                }

                if ($this->apsisCoreHelper->isEnabled($scopeArray['scope'], $scopeArray['id'])) {
                    $this->addRegion($scopeArray);
                }

                $store->resetConfig();
            }
            //Remove AC token mapping
            $setup->getConnection()->delete(
                $setup->getTable('core_config_data'),
                "path='apsis_one_mappings/customer_attribute/ac_token'"
            );
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * Global 32 character long key
     */
    private function generateGlobalKey()
    {
        try {
            $this->apsisCoreHelper->saveConfigValue(
                ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY,
                $this->random->getRandomString(32),
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array $scopeArray
     */
    private function addRegion(array $scopeArray)
    {
        try {
            $this->apsisCoreHelper->saveConfigValue(
                ApsisConfigHelper::ACCOUNTS_OAUTH_REGION,
                Region::REGION_EU,
                $scopeArray['scope'],
                $scopeArray['id']
            );
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param string $topics
     */
    private function updateConsentForProfiles(ModuleDataSetupInterface $setup, string $topics)
    {
        try {
            $setup->getConnection()->update(
                $setup->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE),
                ['topic_subscription' => $topics],
                [
                    "is_subscriber = 1",
                    "subscriber_status = ?" => Subscriber::STATUS_SUBSCRIBED,
                    "subscriber_sync_status = ?" => Profile::SYNC_STATUS_SYNCED
                ]
            );
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $topics
     * @param array $scopeArray
     */
    private function updateConsentListTopicData(string $topics, array $scopeArray)
    {
        try {
            $this->apsisCoreHelper->saveConfigValue(
                ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC,
                $this->getUpdatedConsentData($topics),
                $scopeArray['scope'],
                $scopeArray['id']
            );
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $consentsData
     *
     * @return string
     */
    private function getUpdatedConsentData(string $consentsData)
    {
        try {
            $updatedConsents = '';
            if (! empty($consents = explode(',', $consentsData)) && is_array($consents)) {
                foreach ($consents as $index => $consent) {
                    $subConsentData = explode('|', (string) $consent);
                    if (empty($subConsentData)) {
                        continue;
                    }

                    $subConsentData[2] = str_replace('_', '|', $subConsentData[2]);
                    $consents[$index] = implode('|', $subConsentData);
                }
                $updatedConsents = implode(',', $consents);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $updatedConsents;
    }
}
