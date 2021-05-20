<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile;
use Exception;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;

class SaveUpdate implements ObserverInterface
{
    const REGISTRY_NAME = '_customer_save_after';

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Profile
     */
    private $profileService;

    /**
     * SaveUpdate constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Registry $registry
     * @param Profile $profileService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Registry $registry,
        Profile $profileService
    ) {
        $this->profileService = $profileService;
        $this->registry = $registry;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Customer $customer */
            $customer = $observer->getEvent()->getCustomer();

            $emailReg = $this->registry->registry($customer->getEmail() . self::REGISTRY_NAME);
            if ($emailReg) {
                return $this;
            }
            $this->registry->unregister($customer->getEmail() . self::REGISTRY_NAME);
            $this->registry->register($customer->getEmail() . self::REGISTRY_NAME, $customer->getEmail(), true);

            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $customer->getStoreId());

            if ($account) {
                $profile = $this->profileService->findProfileForCustomer($customer);

                if (! $profile) {
                    $this->profileService->createProfileForCustomer($customer);
                } else {
                    $this->profileService->updateProfileForCustomer($customer, $profile);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $this;
    }
}
