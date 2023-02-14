<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Throwable;
use Magento\Store\Model\ScopeInterface;

class SaveUpdate implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var Profile
     */
    private Profile $profileService;

    /**
     * SaveUpdate constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Profile $profileService
     */
    public function __construct(ApsisCoreHelper $apsisCoreHelper, Profile $profileService)
    {
        $this->profileService = $profileService;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Subscriber $subscriber */
            $subscriber = $observer->getEvent()->getSubscriber();
            if (empty($subscriber) || ! $subscriber->getId() || ! $subscriber->getStoreId()) {
                return $this;
            }

            $store = $this->apsisCoreHelper->getStore($subscriber->getStoreId());
            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());

            if ($account) {
                $profile = $this->profileService->findProfileForSubscriber($subscriber);
                if (! $profile) {
                    $this->profileService->createProfileForSubscriber($subscriber);
                } else {
                    $this->profileService->updateProfileForSubscriber($subscriber, $profile, $store);
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $this;
    }
}
