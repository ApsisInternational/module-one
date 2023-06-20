<?php

namespace Apsis\One\Observer;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\ProfileService;
use Apsis\One\Service\Sub\SubEventService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\AbstractModel;
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
     * @var CustomerRepositoryInterface
     */
    protected CustomerRepositoryInterface $customerRepository;

    /**
     * @var SubEventService
     */
    protected SubEventService $subEventService;

    /**
     * @param ProfileService $profileService
     * @param Registry $registry
     * @param CustomerRepositoryInterface $customerRepository
     * @param SubEventService $subEventService
     */
    public function __construct(
        ProfileService $profileService,
        Registry $registry,
        CustomerRepositoryInterface $customerRepository,
        SubEventService $subEventService
    ) {
        $this->subEventService = $subEventService;
        $this->customerRepository = $customerRepository;
        $this->registry = $registry;
        $this->profileService = $profileService;
    }

    /**
     * @param Observer $observer
     * @param string $type
     *
     * @return Customer|Subscriber|null
     */
    protected function getModelFromObserver(Observer $observer, string $type): Customer|Subscriber|null
    {
        try {
            if ($type === ProfileService::ENTITY_CUSTOMER) {
                /** @var Customer $modelObject */
                $modelObject = $observer->getEvent()->getCustomer();
            } elseif ($type === ProfileService::ENTITY_TYPE_SUBSCRIBER) {
                /** @var Subscriber $modelObject */
                $modelObject = $observer->getEvent()->getSubscriber();
            }

            if (isset($modelObject) && $modelObject instanceof AbstractModel) {
                return $modelObject;
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return null;
    }

    /**
     * @param Subscriber|Customer $modelObject
     * @param string $operation
     *
     * @return $this
     */
    protected function executeForCustomerAndSubscriber(Subscriber|Customer $modelObject, string $operation = ''): static
    {
        try {
            if (empty(static::REGISTRY_NAME)) {
                return $this;
            }

            if (! $this->isOkToProceed($modelObject)) {
                return $this;
            }

            $customerId = $subscriberId = 0;
            if ($modelObject instanceof Customer) {
                $type = ProfileService::ENTITY_CUSTOMER;
                $customerId = (int) $modelObject->getId();
            } elseif ($modelObject instanceof Subscriber) {
                $type = ProfileService::ENTITY_TYPE_SUBSCRIBER;
                $subscriberId = (int) $modelObject->getId();
            }

            $profile = $this->profileService
                ->getProfile(
                    (int) $modelObject->getStoreId(),
                    (string) $modelObject->getEmail(),
                    $customerId,
                    $subscriberId
                );
            if ($profile instanceof ProfileModel && $operation === ProfileService::PROFILE_DELETE) {
                if (! isset($type) || ! in_array($type, ProfileService::ENTITY_TYPES)) {
                    return $this;
                }

                $this->profileService->log(__METHOD__);
                $this->profileService->deleteProfile($profile, $type);
                return $this;
            }

            if ($profile instanceof ProfileModel) {
                $this->profileService->updateProfile($modelObject, $profile);
            } else {
                $this->profileService->createProfile($modelObject);
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return $this;
    }

    /**
     * @param Subscriber|Customer $modelObject
     *
     * @return bool
     */
    private function isOkToProceed(Subscriber|Customer $modelObject): bool
    {
        if (! $modelObject->getEmail() || ! $modelObject->getId() || ! $modelObject->getStoreId() ||
            $this->registry->registry($modelObject->getEmail() . static::REGISTRY_NAME)
        ) {
            $this->registry->unregister($modelObject->getEmail() . static::REGISTRY_NAME);
            return false;
        }
        $this->registry->register($modelObject->getEmail() . static::REGISTRY_NAME, true, true);
        return true;
    }

    /**
     * @param int $customerId
     *
     * @return CustomerInterface|null
     */
    protected function getCustomer(int $customerId): ?CustomerInterface
    {
        try {
            return $this->customerRepository->getById($customerId);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return null;
    }
}
