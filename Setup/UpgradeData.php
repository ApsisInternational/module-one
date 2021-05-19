<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Config\Source\System\Region;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Registry;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;

class UpgradeData implements UpgradeDataInterface
{
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
     * UpgradeData constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Random $random
     * @param EncryptorInterface $encryptor
     * @param Registry $registry
     * @param ProfileResource $profileResource
     * @param EventResource $eventResource
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Random $random,
        EncryptorInterface $encryptor,
        Registry $registry,
        ProfileResource $profileResource,
        EventResource $eventResource
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->random = $random;
        $this->encryptor = $encryptor;
        $this->registry = $registry;
        $this->profileResource = $profileResource;
        $this->eventResource = $eventResource;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->apsisCoreHelper->log(__METHOD__);
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
        $setup->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneNineFive(ModuleDataSetupInterface $setup)
    {
        $this->apsisCoreHelper->log(__METHOD__);
        try {
            //Remove both token and token expiry for force regeneration of token
            $configs = [
                ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN,
                ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE
            ];
            foreach ($configs as $config) {
                $setup->getConnection()->delete(
                    $setup->getTable('core_config_data'),
                    $setup->getConnection()->quoteInto('path = ?', $config)
                );
            }

            //Reset all profile & events to re-sync with failed error
            $whereC = $setup->getConnection()->quoteInto('customer_sync_status = ?', Profile::SYNC_STATUS_FAILED);
            $whereS = $setup->getConnection()->quoteInto('subscriber_sync_status = ?', Profile::SYNC_STATUS_FAILED);
            $whereE = $setup->getConnection()->quoteInto('status = ?', Profile::SYNC_STATUS_FAILED);
            $this->profileResource->resetProfiles($this->apsisCoreHelper, [], [], [$whereC . ' OR ' . $whereS]);
            $this->eventResource->resetEvents($this->apsisCoreHelper, [], [], [$whereE]);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneNineFour(ModuleDataSetupInterface $setup)
    {
        $this->apsisCoreHelper->log(__METHOD__);
        try {
            $this->apsisCoreHelper->log(__METHOD__);
            if ($this->registry->registry(UpgradeSchema::REGISTRY_NAME)) {
                $this->profileResource->updateSubscriberStoreId(
                    $setup->getConnection(),
                    $setup->getTable('newsletter_subscriber'),
                    $setup->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE)
                );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneNineZero(ModuleDataSetupInterface $setup)
    {
        $this->apsisCoreHelper->log(__METHOD__);
        try {
            foreach ($this->apsisCoreHelper->getStores(true) as $store) {
                $oldValue = (string) $store
                    ->getConfig(ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC);
                if (strlen($oldValue) && ! empty($topics = explode(',', $oldValue)) && count($topics)) {
                    $scopeArray = $this->apsisCoreHelper->resolveContext(
                        ScopeInterface::SCOPE_STORES,
                        $store->getId(),
                        ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
                    );
                    $this->apsisCoreHelper->saveConfigValue(
                        ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC,
                        $topics[0],
                        $scopeArray['scope'],
                        $scopeArray['id']
                    );
                }
                $store->resetConfig();
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneFiveZero(ModuleDataSetupInterface $setup)
    {
        $this->apsisCoreHelper->log(__METHOD__);
        try {
            //Take value from older path
            $oldConfigPath = 'apsis_one_sync/sync/endpoint_key';
            $oldValue = $this->apsisCoreHelper->getConfigValue(
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
                $value = $this->apsisCoreHelper->getConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY,
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
            }
            if (strlen($value)) {
                //Encrypt and save in new path
                $this->apsisCoreHelper->saveConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY,
                    $this->encryptor->encrypt($value),
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function upgradeOneTwoZero(ModuleDataSetupInterface $setup)
    {
        $this->apsisCoreHelper->log(__METHOD__);
        $this->generateGlobalKey();
        foreach ($this->apsisCoreHelper->getStores(true) as $store) {
            $topics = (string) $store->getConfig(ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC);
            $scopeArray = $this->apsisCoreHelper->resolveContext(
                ScopeInterface::SCOPE_STORES,
                $store->getId(),
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
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
    }

    /**
     * Global 32 character long key
     */
    private function generateGlobalKey()
    {
        try {
            $this->apsisCoreHelper->saveConfigValue(
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY,
                $this->random->getRandomString(32),
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param array $scopeArray
     */
    private function addRegion(array $scopeArray)
    {
        $this->apsisCoreHelper->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_REGION,
            Region::REGION_EU,
            $scopeArray['scope'],
            $scopeArray['id']
        );
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param string $topics
     * @param array $scopeArray
     */
    private function updateConsentListTopicData(string $topics, array $scopeArray)
    {
        $this->apsisCoreHelper->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC,
            $this->getUpdatedConsentData($topics),
            $scopeArray['scope'],
            $scopeArray['id']
        );
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
                    $subConsentData = explode('|', $consent);
                    $subConsentData[2] = str_replace('_', '|', $subConsentData[2]);
                    $consents[$index] = implode('|', $subConsentData);
                }
                $updatedConsents = implode(',', $consents);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $updatedConsents;
    }
}
