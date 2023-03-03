<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Throwable;

class Remove implements ObserverInterface
{
    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $apsislogHelper;

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
        $this->apsislogHelper = $apsisLogHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            $customer = $observer->getEvent()->getCustomer();
            if (empty($customer) || ! $customer->getEmail() || ! $customer->getId() || ! $customer->getStoreId()) {
                return $this;
            }

            if ($profile = $this->profileService->findProfileForCustomer($customer)) {
                $this->apsislogHelper->log(__METHOD__);
                $this->profileService->handleProfileDeleteOperation($profile);
            }
        } catch (Throwable $e) {
            $this->apsislogHelper->logError(__METHOD__, $e);
        }
        return $this;
    }
}
