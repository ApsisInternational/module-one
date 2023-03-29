<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Newsletter\Model\Subscriber;
use Throwable;

class SaveUpdate implements ObserverInterface
{
    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $apsisLogHelper;

    /**
     * @var Profile
     */
    private Profile $profileService;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * SaveUpdate constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param Profile $profileService
     * @param Registry $registry
     */
    public function __construct(ApsisLogHelper $apsisLogHelper, Profile $profileService, Registry $registry)
    {
        $this->registry = $registry;
        $this->profileService = $profileService;
        $this->apsisLogHelper = $apsisLogHelper;
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

            if ($this->registry->registry($subscriber->getEmail() . '_subscription')) {
                $this->registry->unregister($subscriber->getEmail() . '_subscription');
                return $this;
            }

            $profile = $this->profileService->findProfileForSubscriber($subscriber);
            if (! $profile) {
                $this->profileService->createProfileForSubscriber($subscriber);
            } else {
                $this->profileService->updateProfileForSubscriber($subscriber, $profile);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }

        return $this;
    }
}
