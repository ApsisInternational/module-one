<?php

namespace Apsis\One\Model\Sync\Profiles\Subscribers;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Newsletter\Model\Subscriber as MagentoSubscriber;

class Subscriber
{
    /**
     * @var array
     */
    private $subscriberData = [];

    /**
     * @var MagentoSubscriber
     */
    private $subscriber;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Subscriber constructor.
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function __construct(ApsisCoreHelper $apsisCoreHelper)
    {
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param array $mappingHash
     * @param MagentoSubscriber $subscriber
     *
     * @return $this
     */
    public function setSubscriberData(array $mappingHash, MagentoSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
        foreach ($mappingHash as $key) {
            $function = 'get';
            $exploded = explode('_', $key);
            foreach ($exploded as $one) {
                $function .= ucfirst($one);
            }
            $this->subscriberData[$key] = call_user_func(['self', $function]);
        }
        return $this;
    }

    /**
     * @param array $consentLists
     *
     * @return $this
     */
    public function setConsentListData(array $consentLists)
    {
        foreach ($consentLists as $consentList) {
            $this->subscriberData[$consentList] = 1;
        }
        return $this;
    }

    /**
     * Contact data array.
     *
     * @return array
     */
    public function toCSVArray()
    {
        return array_values($this->subscriberData);
    }

    /**
     * @return string
     */
    private function getIntegrationUid()
    {
        return (string) $this->subscriber->getIntegrationUid();
    }

    /**
     * @return string
     */
    private function getEmail()
    {
        return (string) $this->subscriber->getEmail();
    }

    /**
     * @return int|string
     */
    private function getStoreId()
    {
        return ($this->subscriber->getStoreId()) ? (int) $this->subscriber->getStoreId() : '';
    }

    /**
     * @return string
     */
    private function getStoreName()
    {
        return (string) $this->subscriber->getStoreName();
    }

    /**
     * @return int|string
     */
    private function getWebsiteId()
    {
        return ($this->subscriber->getWebsiteId()) ? (int) $this->subscriber->getWebsiteId() : '';
    }

    /**
     * @return string
     */
    private function getWebsiteName()
    {
        return (string) $this->subscriber->getWebsiteName();
    }

    /**
     * @return int|string
     */
    private function getSubscriberId()
    {
        return ($this->subscriber->getSubscriberId()) ? (int) $this->subscriber->getSubscriberId() : '';
    }

    /**
     * @return string
     */
    private function getSubscriberStatus()
    {
        if ($this->subscriber->getSubscriberStatus() == MagentoSubscriber::STATUS_SUBSCRIBED) {
            $status = 'subscribed';
        } else {
            $status = 'unsubscribed';
        }

        return $status;
    }

    /**
     * @return int|string
     */
    private function getChangeStatusAt()
    {
        return ($this->subscriber->getChangeStatusAt()) ?
            (int) $this->apsisCoreHelper->formatDateForPlatformCompatibility($this->subscriber->getChangeStatusAt()) :
            '';
    }
}
