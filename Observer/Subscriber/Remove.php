<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Model\Profile as ProfileModel;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Remove implements ObserverInterface
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
     * Remove constructor.
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

            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $subscriber->getStoreId());
            if ($account && $profile = $this->profileService->findProfileForSubscriber($subscriber)) {
                $this->apsisCoreHelper->log(__METHOD__);
                $this->profileService->handleDeleteOperationByType($profile, ProfileModel::TYPE_SUBSCRIBER);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $this;
    }
}
