<?php

namespace Apsis\One\Plugin\Customer;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Event;
use Apsis\One\Model\Service\Profile;
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
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var Event
     */
    private Event $eventService;

    /**
     * @var Profile
     */
    private Profile $profileService;

    /**
     * Account constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     * @param Profile $profileService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        CustomerRepositoryInterface $customerRepository,
        ProfileCollectionFactory $profileCollectionFactory,
        Event $eventService,
        Profile $profileService
    ) {
        $this->eventService = $eventService;
        $this->profileService = $profileService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->apsisCoreHelper = $apsisCoreHelper;
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

            $store = $this->apsisCoreHelper->getStore($customer->getStoreId());
            $profile = $this->profileCollectionFactory->create()
                ->loadByCustomerId($customer->getId());
            if (! $profile) {
                return $result;
            }

            if (isset($data['last_login_at'])) {
                $this->profileService->mergeMagentoProfileWithWebProfile($profile, $store, $customer);
                $this->eventService->registerCustomerLoginEvent($logger, $customerId, $profile, $customer);
                $profile->setHasDataChanges(true);
                $profile->save();
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $result;
    }
}
