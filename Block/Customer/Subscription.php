<?php

namespace Apsis\One\Block\Customer;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;

/**
 * NewsletterPreferences block
 *
 * @api
 */
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
        $customer = $this->customerSession->getCustomer();
        $selectedConsentTopics = (string) $this->apsisCoreHelper->getStoreConfig(
            $customer->getStore(),
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
        );
        /** @var Subscriber $subscriber */
        if (strlen($selectedConsentTopics) &&
            ! empty($subscriber = $this->subscriberFactory->create()->loadByCustomerId($customer->getId())) &&
            $subscriber->getId()) {
            $profile = $this->profileCollectionFactory->create()
                ->loadBySubscriberId($subscriber->getSubscriberId());
            $sortedTopicArr = ($profile) ? $this->getConsentListsWithTopicsArr(
                $selectedConsentTopics,
                $this->getProfileTopicArr((string) $profile->getTopicSubscription())
            ) : [];
        }
        return $sortedTopicArr;
    }

    /**
     * @param string $profileTopics
     *
     * @return array
     */
    private function getProfileTopicArr(string $profileTopics)
    {
        $topicArr = [];
        if (strlen($profileTopics) > 1) {
            $topics = explode(',', $profileTopics);
            foreach ($topics as $topic) {
                $topicMapping = explode('|', $topic);
                if (empty($topicMapping) || ! isset($topicMapping[1])) {
                    continue;
                }
                $topicArr[] = $topicMapping[1];
            }
        }
        return $topicArr;
    }

    /**
     * @param string $topicMappings
     * @param array $profileTopics
     *
     * @return array
     */
    private function getConsentListsWithTopicsArr(string $topicMappings, array $profileTopics)
    {
        $topicMappings = explode(',', $topicMappings);
        $topicMappingsArr = [];
        foreach ($topicMappings as $topicMappingString) {
            $topicMapping = explode('|', $topicMappingString);
            if (empty($topicMapping) || count($topicMapping) < 4) {
                continue;
            }
            $topic = [
                'value' => $topicMapping[0] . '|' . $topicMapping[1],
                'name' => $topicMapping[3],
                'consent' => in_array($topicMapping[1], $profileTopics)
            ];
            if (empty($topicMappingsArr[$topicMapping[0]]['topics'])) {
                $topicMappingsArr[$topicMapping[0]] = [
                    'name' => $topicMapping[2],
                    'topics' => [$topic]
                ];
                continue;
            }
            $topicMappingsArr[$topicMapping[0]]['topics'][] = $topic;
        }
        return $topicMappingsArr;
    }
}
