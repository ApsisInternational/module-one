<?php

namespace Apsis\One\Observer\Customer;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Exception;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;

class SaveUpdate implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

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
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Registry $registry
     * @param Profile $profileService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        Registry $registry,
        Profile $profileService
    ) {
        $this->profileService = $profileService;
        $this->registry = $registry;
        $this->profileCollectionFactory = $profileCollectionFactory;
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

            $emailReg = $this->registry->registry($customer->getEmail() . '_customer_save_after');
            if ($emailReg) {
                return $this;
            }
            $this->registry->unregister($customer->getEmail() . '_customer_save_after');
            $this->registry->register($customer->getEmail() . '_customer_save_after', $customer->getEmail());

            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $customer->getStoreId());

            if ($account) {
                $found = $this->profileCollectionFactory->create()->loadByCustomerId($customer->getEntityId());
                $profile = ($found) ? $found : $this->profileCollectionFactory->create()->loadByEmailAndStoreId(
                    $customer->getEmail(),
                    $customer->getStoreId()
                );

                if (! $profile) {
                    $this->profileService->createProfileForCustomer($customer);
                } else {
                    $this->profileService->updateProfileForCustomer($customer, $profile);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $this;
    }
}
