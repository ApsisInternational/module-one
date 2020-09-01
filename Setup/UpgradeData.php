<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Profile;
use Exception;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * UpgradeData constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function __construct(ApsisCoreHelper $apsisCoreHelper)
    {
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            foreach ($this->apsisCoreHelper->getStores(true) as $store) {
                $topics = (string) $store->getConfig(ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC);
                if (strlen($topics)) {
                    $this->updateConsentListTopicData($store, $topics);
                    $this->updateConsentForProfiles($setup, $topics);
                }
            }
        }
        $setup->endSetup();
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
     * @param StoreInterface $store
     * @param string $topics
     */
    private function updateConsentListTopicData(StoreInterface $store, string $topics)
    {
        $scopeArray = $this->apsisCoreHelper->resolveContext(
            ScopeInterface::SCOPE_STORES,
            $store->getId(),
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
        );
        $this->apsisCoreHelper->saveConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC,
            $this->getUpdatedConsentData($topics),
            $scopeArray['scope'],
            $scopeArray['id']
        );
        $store->resetConfig();
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
