<?php

namespace Apsis\One\Model\Sync\Profiles\Subscribers;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Sync\Profiles\ProfileDataInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Newsletter\Model\Subscriber as MagentoSubscriber;

class Subscriber implements ProfileDataInterface
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
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Subscriber constructor.
     *
     * @param ApsisDateHelper $apsisDateHelper
     */
    public function __construct(ApsisDateHelper $apsisDateHelper)
    {
        $this->apsisDateHelper = $apsisDateHelper;
    }

    /**
     * @param array $mappingHash
     * @param AbstractModel $subscriber
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return $this
     */
    public function setModelData(
        array $mappingHash,
        AbstractModel $subscriber,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        $this->subscriber = $subscriber;
        $this->apsisCoreHelper = $apsisCoreHelper;
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
     * @param array $topics
     * @param int $consent
     *
     * @return $this
     */
    public function setConsentTopicData(array $topics, int $consent)
    {
        foreach ($topics as $topic) {
            $this->subscriberData[$topic] = $consent;
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
    private function getProfileKey()
    {
        return (string) $this->subscriber->getProfileKey();
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
        if ((int) $this->subscriber->getSubscriberStatus() === MagentoSubscriber::STATUS_SUBSCRIBED) {
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
            (int) $this->apsisDateHelper->formatDateForPlatformCompatibility($this->subscriber->getChangeStatusAt()) :
            '';
    }
}
