<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Config\Source\System\Region;
use Apsis\One\Model\Profile;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Random
     */
    private $random;

    /**
     * UpgradeData constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Random $random
     */
    public function __construct(ApsisCoreHelper $apsisCoreHelper, Random $random)
    {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->random = $random;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.2.0', '<')) {
            $this->updateOneTwoZero($setup);
        }
        $setup->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function updateOneTwoZero(ModuleDataSetupInterface $setup)
    {
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
