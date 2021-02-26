<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Exception;
use Magento\Store\Model\ScopeInterface;

class SaveUpdate implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Profile
     */
    private $profileService;

    /**
     * SaveUpdate constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Profile $profileService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Profile $profileService
    ) {
        $this->profileService = $profileService;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Subscriber $subscriber */
            $subscriber = $observer->getEvent()->getSubscriber();
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $this;
    }
}
