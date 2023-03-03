<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Throwable;

class SaveUpdate implements ObserverInterface
{
    const REGISTRY_NAME = '_customer_save_after';

    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $apsisLogHelper;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var Profile
     */
    private Profile $profileService;

    /**
     * SaveUpdate constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param Registry $registry
     * @param Profile $profileService
     */
    public function __construct(ApsisLogHelper $apsisLogHelper, Registry $registry, Profile $profileService)
    {
        $this->profileService = $profileService;
        $this->registry = $registry;
        $this->apsisLogHelper = $apsisLogHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Customer $customer */
            $customer = $observer->getEvent()->getCustomer();
            if (empty($customer) || ! $customer->getEmail() || ! $customer->getId() || ! $customer->getStoreId()) {
                return $this;
            }

            $emailReg = $this->registry->registry($customer->getEmail() . self::REGISTRY_NAME);
            if ($emailReg) {
                return $this;
            }

            $this->registry->unregister($customer->getEmail() . self::REGISTRY_NAME);
            $this->registry->register($customer->getEmail() . self::REGISTRY_NAME, $customer->getEmail(), true);
            $profile = $this->profileService->findProfileForCustomer($customer);

            if (! $profile) {
                $this->profileService->createProfileForCustomer($customer);
            } else {
                $this->profileService->updateProfileForCustomer($customer, $profile);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return $this;
    }
}
