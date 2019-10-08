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
    private function getEmail()
    {
        return (string) $this->subscriber->getEmail();
    }

    /**
     * @return int
     */
    private function getStoreId()
    {
        return (int) $this->subscriber->getStoreId();
    }

    /**
     * @return string
     */
    private function getStoreName()
    {
        return (string) $this->subscriber->getStoreName();
    }

    /**
     * @return int
     */
    private function getWebsiteId()
    {
        return (int) $this->subscriber->getWebsiteId();
    }

    /**
     * @return string
     */
    private function getWebsiteName()
    {
        return (string) $this->subscriber->getWebsiteName();
    }

    /**
     * @return int
     */
    private function getSubscriberId()
    {
        return (int) $this->subscriber->getSubscriberId();
    }

    /**
     * @return string
     */
    private function getSubscriberStatus()
    {
        $statusId = $this->subscriber->getSubscriberStatus();
        $status = 'subscribed';
        if ($statusId === MagentoSubscriber::STATUS_UNSUBSCRIBED) {
            $status = 'unsubscribed';
        }

        return (string) $status;
    }

    /**
     * @return string
     */
    private function getChangeStatusAt()
    {
        return (string) ($this->subscriber->getChangeStatusAt()) ?
            $this->apsisCoreHelper->formatDateForPlatformCompatibility($this->subscriber->getChangeStatusAt()) : '';
    }
}
