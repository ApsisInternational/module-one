<?php

namespace Apsis\One\Block\Customer;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Subscription extends Template
{
    const CUSTOMER_NEWSLETTER_SAVE_URL = 'apsis/customer/subscription';

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var Session
     */
    private Session $customerSession;

    /**
     * @var SubscriberFactory
     */
    private SubscriberFactory $subscriberFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * NewsletterPreferences constructor.
     *
     * @param Template\Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Session $customerSession
     * @param SubscriberFactory $subscriberFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        Session $customerSession,
        SubscriberFactory $subscriberFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        array $data = []
    ) {
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->subscriberFactory = $subscriberFactory;
        $this->customerSession = $customerSession;
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context, $data);
        $this->_isScopePrivate = true;
    }

    /**
     * @return null
     */
    public function getCacheLifetime()
    {
        return null;
    }

    /**
     * @return string
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl(self::CUSTOMER_NEWSLETTER_SAVE_URL);
    }

    /**
     * @return array
     */
    public function getTopicsToShow(): array
    {
        $sortedTopicArr = [];

        try {
            $customer = $this->customerSession->getCustomer();
            if (empty($customer->getId())) {
                return $sortedTopicArr;
            }

            $topicMappings = explode(',', (string)$this->apsisCoreHelper->getStoreConfig(
                $customer->getStore(),
                ApsisConfigHelper::SYNC_SETTING_ADDITIONAL_TOPIC
            ));
            $subscriber = $this->subscriberFactory->create()->loadByCustomerId($customer->getId());

            if (empty($topicMappings) || empty($subscriber->getId())) {
                return $sortedTopicArr;
            }

            $profile = $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getId());
            if (empty($profile)) {
                return $sortedTopicArr;
            }

            $sortedTopicArr = $this->getConsentListsWithTopicsArr(
                $topicMappings,
                $this->getProfileOptinTopicArr($profile)
            );

            if (!empty($sortedTopicArr)) {
                $this->customerSession->setPreUpdateConsents($sortedTopicArr);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $sortedTopicArr;
    }

    /**
     * @param DataObject|Profile $profile
     *
     * @return array
     */
    private function getProfileOptinTopicArr(DataObject|Profile $profile): array
    {
        $topicArr = [];

        try {
            $store = $this->apsisCoreHelper->getStore($profile->getSubscriberStoreId());
            $client = $this->apsisCoreHelper->getApiClient(
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION
            );
            $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);

            if (empty($client) || empty($sectionDiscriminator) || empty($keySpaceDiscriminator)) {
                return $topicArr;
            }

            $consents = $client->getConsents(
                $keySpaceDiscriminator,
                $profile->getIntegrationUid(),
                $sectionDiscriminator
            );
            if (! $consents || empty($consents->items)) {
                return $topicArr;
            }

            foreach ($consents->items as $consent) {
                $topicArr[] = $consent->topic_discriminator;
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $topicArr;
    }

    /**
     * @param array $topicMappings
     * @param array $profileTopics
     *
     * @return array
     */
    private function getConsentListsWithTopicsArr(array $topicMappings, array $profileTopics): array
    {
        $topicMappingsArr = [];

        try {
            foreach ($topicMappings as $topicMappingString) {
                $topicMapping = explode('|', (string) $topicMappingString);

                //Count should always be 2, if not then not a valid config.
                if (empty($topicMapping) || count($topicMapping) < 2) {
                    continue;
                }

                //index 0 is always topic discriminator, 1 is always topic name
                $topicMappingsArr[] = [
                    'value' => $topicMapping[0],
                    'name' => $topicMapping[1],
                    'consent' => in_array($topicMapping[0], $profileTopics)
                ];
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $topicMappingsArr;
    }
}
