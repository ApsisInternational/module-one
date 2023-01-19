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
    private array $subscriberData = [];

    /**
     * @var MagentoSubscriber
     */
    private MagentoSubscriber $subscriber;

    /**
     * @var ApsisDateHelper
     */
    private ApsisDateHelper $apsisDateHelper;

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
     * @inheritdoc
     */
    public function setModelData(
        array $mappingHash,
        AbstractModel|MagentoSubscriber $model,
        ApsisCoreHelper $apsisCoreHelper
    ): static {
        $this->subscriber = $model;

        foreach ($mappingHash as $key) {
            $function = 'get';
            $exploded = explode('_', (string) $key);

            foreach ($exploded as $one) {
                $function .= ucfirst($one);
            }

            $this->subscriberData[(string) $key] = call_user_func(['self', $function]);
        }

        return $this;
    }

    /**
     * @param array $topics
     * @param int $consent
     *
     * @return $this
     */
    public function setConsentTopicData(array $topics, int $consent): static
    {
        foreach ($topics as $topic) {
            $this->subscriberData[$topic] = $consent;
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function toCSVArray(): array
    {
        return array_values($this->subscriberData);
    }

    /**
     * @return string
     */
    private function getProfileKey(): string
    {
        return (string) $this->subscriber->getProfileKey();
    }

    /**
     * @return string
     */
    private function getIntegrationUid(): string
    {
        return (string) $this->subscriber->getIntegrationUid();
    }

    /**
     * @return string
     */
    private function getEmail(): string
    {
        return (string) $this->subscriber->getEmail();
    }

    /**
     * @return int|string
     */
    private function getStoreId(): int|string
    {
        return ($this->subscriber->getStoreId()) ? (int) $this->subscriber->getStoreId() : '';
    }

    /**
     * @return string
     */
    private function getStoreName(): string
    {
        return (string) $this->subscriber->getStoreName();
    }

    /**
     * @return int|string
     */
    private function getWebsiteId(): int|string
    {
        return ($this->subscriber->getWebsiteId()) ? (int) $this->subscriber->getWebsiteId() : '';
    }

    /**
     * @return string
     */
    private function getWebsiteName(): string
    {
        return (string) $this->subscriber->getWebsiteName();
    }

    /**
     * @return int|string
     */
    private function getSubscriberId(): int|string
    {
        return ($this->subscriber->getSubscriberId()) ? (int) $this->subscriber->getSubscriberId() : '';
    }

    /**
     * @return string
     */
    private function getSubscriberStatus(): string
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
    private function getChangeStatusAt(): int|string
    {
        return ($this->subscriber->getChangeStatusAt()) ?
            (int) $this->apsisDateHelper->formatDateForPlatformCompatibility($this->subscriber->getChangeStatusAt()) :
            '';
    }
}
