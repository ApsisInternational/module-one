<?php

namespace Apsis\One\Plugin\Customer;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\ResourceModel\Profile\ProfileCollection;
use Apsis\One\Model\ResourceModel\ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\ProfileCollectionFactory;
use Apsis\One\Service\ApiService;
use Apsis\One\Service\Sub\SubEventService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Logger as CustomerLogger;
use Throwable;

class LoggerPlugin
{
    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var SubEventService
     */
    private SubEventService $subEventService;

    /**
     * @var ApiService
     */
    private ApiService $apiService;

    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param SubEventService $subEventService
     * @param ApiService $apiService
     * @param ProfileResource $profileResource
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ProfileCollectionFactory $profileCollectionFactory,
        SubEventService $subEventService,
        ApiService $apiService,
        ProfileResource $profileResource
    ) {
        $this->profileResource = $profileResource;
        $this->subEventService = $subEventService;
        $this->apiService = $apiService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param CustomerLogger $logger
     * @param CustomerLogger $result
     * @param int $customerId
     * @param array $data
     *
     * @return CustomerLogger
     */
    public function afterLog(
        CustomerLogger $logger,
        CustomerLogger $result,
        int $customerId,
        array $data
    ): CustomerLogger {
        try {
            $customer = $this->customerRepository->getById($customerId);
            if (empty($customer->getId())) {
                return $result;
            }

            $store = $this->apiService->getStore($customer->getStoreId());
            /** @var ProfileModel $profile */
            $profile = $this->getProfileCollection()
                ->getFirstItemFromCollection('customer_id', $customer->getId());
            if (empty($profile)) {
                return $result;
            }

            if (isset($data['last_login_at'])) {
                $this->apiService->mergeProfile($store, $profile, $customer);
                $this->subEventService
                    ->registerCustomerLoginEvent($logger, $customerId, $profile, $customer, $this->apiService);
                $profile->setHasDataChanges(true);
                $this->profileResource->save($profile);
            }
        } catch (Throwable $e) {
            $this->apiService->logError(__METHOD__, $e);
        }

        return $result;
    }

    /**
     * @return ProfileCollection
     */
    public function getProfileCollection(): ProfileCollection
    {
        return $this->profileCollectionFactory->create();
    }
}
