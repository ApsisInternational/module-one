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
    private $apsisCoreHelper;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @inheritdoc
     */
    protected $_isScopePrivate = true;

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
    public function getSaveUrl()
    {
        return $this->getUrl(self::CUSTOMER_NEWSLETTER_SAVE_URL);
    }

    /**
     * @return array
     */
    public function getConsentListTopicsToShow()
    {
        $sortedTopicArr = [];

        try {
            $customer = $this->customerSession->getCustomer();
            if (empty($customer->getId())) {
                return $sortedTopicArr;
            }

            $topicMappings = explode(',', (string)$this->apsisCoreHelper->getStoreConfig($customer->getStore(),
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
                $this->getProfileTopicArr($profile, $topicMappings)
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
     * @param DataObject $profile
     * @param array $topicMappings
     *
     * @return array
     */
    private function getProfileTopicArr(DataObject $profile, array $topicMappings)
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

            if (empty($client) || empty($sectionDiscriminator)) {
                return $topicArr;
            }

            foreach ($topicMappings as $topicMappingString) {
                $topicMapping = explode('|', $topicMappingString);

                //Count should always be 4, if not then not a valid config.
                if (empty($topicMapping) || count($topicMapping) < 4) {
                    continue;
                }

                if ($consentListDiscriminator = $topicMapping[0]) {
                    $consents = $client->getOptInConsents(
                        Profile::EMAIL_CHANNEL_DISCRIMINATOR,
                        $profile->getEmail(),
                        $sectionDiscriminator,
                        $consentListDiscriminator
                    );

                    if (! empty($consents->items)) {
                        foreach ($consents->items as $consent) {
                            $topicArr[] = $consent->topic_discriminator;
                        }
                    }
                }
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
    private function getConsentListsWithTopicsArr(array $topicMappings, array $profileTopics)
    {
        $topicMappingsArr = [];

        try {
            foreach ($topicMappings as $topicMappingString) {
                $topicMapping = explode('|', $topicMappingString);

                //Count should always be 4, if not then not a valid config.
                if (empty($topicMapping) || count($topicMapping) < 4) {
                    continue;
                }

                //index 0 is always CLD, 1 is always TD, 2 is always list name and 3 is always  topic name
                $topic = [
                    'value' => $topicMapping[0] . '|' . $topicMapping[1],
                    'name' => $topicMapping[3],
                    'consent' => in_array($topicMapping[1], $profileTopics)
                ];

                //Prepare array
                if (empty($topicMappingsArr[$topicMapping[0]]['topics'])) {
                    $topicMappingsArr[$topicMapping[0]] = [
                        'name' => $topicMapping[2],
                        'topics' => [$topic]
                    ];
                    continue;
                }

                $topicMappingsArr[$topicMapping[0]]['topics'][] = $topic;
            }

        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $topicMappingsArr;
    }
}
