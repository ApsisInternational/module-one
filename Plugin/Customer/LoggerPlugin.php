<?php

namespace Apsis\One\Plugin\Customer;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Logger as CustomerLogger;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Apsis\One\Model\Service\Event;
use Apsis\One\Model\Service\Profile;

class LoggerPlugin
{
    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Event
     */
    private $eventService;

    /**
     * @var Profile
     */
    private $profileService;

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
     *
     * @param CustomerLogger $result
     * @param int $customerId
     * @param array $data
     *
     * @return CustomerLogger
     */
    public function afterLog(CustomerLogger $logger, $result, $customerId, array $data)
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            if (empty($customer->getId())) {
                return $result;
            }

            $store = $this->apsisCoreHelper->getStore($customer->getStoreId());
            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            if (! $account) {
                return $result;
            }

            $profile = $this->profileCollectionFactory->create()
                ->loadByEmailAndStoreId($customer->getEmail(), $customer->getStoreId());
            if (! $profile) {
                return $result;
            }

            if (isset($data['last_login_at'])) {
                $this->profileService->mergeMagentoProfileWithWebProfile($profile, $store, $customer);
                if ($this->isEventEnabled($store)) {
                    $this->eventService->registerCustomerLoginEvent($logger, $customerId, $profile, $customer);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $result;
    }

    /**
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isEventEnabled(StoreInterface $store)
    {
        return (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_LOGIN
        );
    }
}
