<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Throwable;

class Remove implements ObserverInterface
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
     * Remove constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param Profile $profileService
     */
    public function __construct(ApsisLogHelper $apsisLogHelper, Profile $profileService)
    {
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

            if ($profile = $this->profileService->findProfileForSubscriber($subscriber)) {
                $this->apsisLogHelper->log(__METHOD__);
                $this->profileService->handleProfileDeleteOperation($profile);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }

        return $this;
    }
}
