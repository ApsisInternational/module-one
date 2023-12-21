<?php

namespace Apsis\One\Observer;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\ProfileService;
use Apsis\One\Service\Sub\SubEventService;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Newsletter\Model\Subscriber;
use Throwable;

abstract class AbstractObserver implements ObserverInterface
{
    const REGISTRY_NAME = '';

    /**
     * @var ProfileService
     */
    protected ProfileService $profileService;

    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @var CustomerRegistry
     */
    protected CustomerRegistry $customerRegistry;

    /**
     * @var SubEventService
     */
    protected SubEventService $subEventService;

    /**
     * @var Session
     */
    protected Session $session;

    /**
     * @param ProfileService $profileService
     * @param Registry $registry
     * @param CustomerRegistry $customerRegistry
     * @param SubEventService $subEventService
     * @param Session $session
     */
    public function __construct(
        ProfileService $profileService,
        Registry $registry,
        CustomerRegistry $customerRegistry,
        SubEventService $subEventService,
        Session $session
    ) {
        $this->subEventService = $subEventService;
        $this->customerRegistry = $customerRegistry;
        $this->registry = $registry;
        $this->profileService = $profileService;
        $this->session = $session;
    }

    /**
     * @param Observer $observer
     * @param string $eType
     * @param string $op
     *
     * @return $this
     */
    protected function executeForCustomerAndSubscriber(Observer $observer, string $eType, string $op = ''): static
    {
        try {
            $mObject = $this->getModelFromObserver($observer, $eType);
            if (empty(static::REGISTRY_NAME) || $mObject === null) {
                return $this;
            }

            if (! $this->isRequiredObjectPropertiesExist($mObject) || ! $this->checkRegistry($mObject)) {
                return $this;
            }

            $customerId = $subscriberId = 0;
            if ($mObject instanceof Customer) {
                $type = ProfileService::TYPE_CUSTOMER;
                $customerId = (int) $mObject->getId();
            } else {
                $type = ProfileService::TYPE_SUBSCRIBER;
                $subscriberId = (int) $mObject->getId();
            }

            $profile = $this->profileService
                ->getProfile((int) $mObject->getStoreId(), (string) $mObject->getEmail(), $customerId, $subscriberId);
            if ($profile instanceof ProfileModel) {
                if ($observer->getEvent()->getName() === 'customer_address_save_after') {
                    $this->session->setApsisProceed(true);
                    return $this;
                }

                if ($observer->getEvent()->getName() === 'customer_address_load_after') {
                    if ($this->session->getApsisProceed()) {
                        $this->session->unsApsisProceed();
                        $profile->setHasDataChanges(true);
                        $this->profileService->subProfileService->profileResource->save($profile);
                    }
                    return $this;
                }

                if ($op === ProfileService::OP_DELETE) {
                    if (! isset($type) || ! in_array($type, ProfileService::ENTITY_TYPES)) {
                        return $this;
                    }
                    $this->profileService->deleteProfile($profile, $type);
                    return $this;
                }
                $this->profileService->updateProfile($mObject, $profile);
            } else {
                $this->profileService->createProfile($mObject);
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return $this;
    }

    /**
     * @param Observer $observer
     * @param string $eType
     *
     * @return Customer|Subscriber|null
     */
    protected function getModelFromObserver(Observer $observer, string $eType): Customer|Subscriber|null
    {
        try {
            if ($eType === ProfileService::TYPE_CUSTOMER) {
                /** @var Customer $modelObject */
                $modelObject = $observer->getEvent()->getCustomer();
            } elseif ($eType === ProfileService::TYPE_SUBSCRIBER) {
                /** @var Subscriber $modelObject */
                $modelObject = $observer->getEvent()->getSubscriber();
            } elseif ($eType === ProfileService::TYPE_ADDRESS && $observer->getCustomerAddress()->getCustomerId()) {
                /** @var Customer $modelObject */
                $modelObject = $observer->getCustomerAddress()->getCustomer();
            }

            if (isset($modelObject) && ($modelObject instanceof Customer || $modelObject instanceof Subscriber)) {
                return $modelObject;
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return null;
    }

    /**
     * @param Subscriber|Customer $modelObject
     *
     * @return bool
     */
    protected function checkRegistry(Subscriber|Customer $modelObject): bool
    {
        if ($this->registry->registry($modelObject->getEmail() . static::REGISTRY_NAME)) {
            $this->registry->unregister($modelObject->getEmail() . static::REGISTRY_NAME);
            return false;
        }
        $this->registry->register($modelObject->getEmail() . static::REGISTRY_NAME, true, true);
        return true;
    }

    /**
     * @param Subscriber|Customer $modelObject
     *
     * @return bool
     */
    protected function isRequiredObjectPropertiesExist(Subscriber|Customer $modelObject): bool
    {
        if (! $modelObject->getEmail() || ! $modelObject->getId() || ! $modelObject->getStoreId()) {
            return false;
        }
        return true;
    }

    /**
     * @param int $customerId
     *
     * @return Customer|null
     */
    protected function getCustomer(int $customerId): ?Customer
    {
        try {
            return $this->customerRegistry->retrieve($customerId);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return null;
    }
}
