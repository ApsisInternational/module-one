<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

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
            $customer = $observer->getEvent()->getCustomer();
            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $customer->getStoreId());

            if ($account && $profile = $this->profileService->findProfileForCustomer($customer)) {
                $this->profileService->handleCustomerDeleteForProfile($profile);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $this;
    }
}
