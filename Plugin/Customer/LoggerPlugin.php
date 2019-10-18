<?php

namespace Apsis\One\Plugin\Customer;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Event;
use Apsis\One\Model\EventFactory;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Exception;
use Magento\Customer\Model\Logger as CustomerLogger;
use Magento\Customer\Model\Log as CustomerLog;
use Magento\Store\Model\ScopeInterface;

class LoggerPlugin
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var CustomerLogger
     */
    private $customerLogger;

    /**
     * Account constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventFactory $eventFactory
     * @param EventResource $eventResource
     * @param CustomerLogger $customerLogger
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventFactory $eventFactory,
        EventResource $eventResource,
        CustomerLogger $customerLogger
    ) {
        $this->customerLogger = $customerLogger;
        $this->eventFactory = $eventFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventResource = $eventResource;
    }

    /**
     * @param CustomerLogger $logger
     *
     * @param CustomerLogger $result
     * @param int $customerId
     * @param array $data
     * @return CustomerLogger
     */
    public function afterLog(CustomerLogger $logger, $result, $customerId, array $data)
    {
        $customer = $this->apsisCoreHelper->getCustomerById($customerId);
        if ($this->isOkToProceed() && $customer && isset($data['last_login_at'])) {
            /** @var CustomerLog $customerLog */
            $customerLog = $this->customerLogger->get($customerId);

            $eventModel = $this->eventFactory->create()
                ->setEventType(Event::EVENT_TYPE_CUSTOMER_LOGIN)
                ->setEventData($this->apsisCoreHelper->serialize($this->getDataArr($customerLog)))
                ->setCustomerId($customerLog->getCustomerId())
                ->setStoreId($this->apsisCoreHelper->getStore()->getId())
                ->setEmail($customer->getEmail())
                ->setStatus(Profile::SYNC_STATUS_PENDING);

            try {
                $this->eventResource->save($eventModel);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                return $result;
            }
        }

        return $result;
    }

    /**
     * @param CustomerLog $customerLog
     *
     * @return array
     */
    private function getDataArr(CustomerLog $customerLog)
    {
        $data = [
            'customer_id' => (int) $customerLog->getCustomerId(),
            'login_at' => (string) $this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($customerLog->getLastLoginAt()),
            'last_logout_at' => (string) $this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($customerLog->getLastLogoutAt()),
            'last_visit_at' => (string) $this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($customerLog->getLastVisitAt()),
            'website_name' => (string) $this->apsisCoreHelper
                ->getWebsiteNameFromStoreId(),
            'store_name' => (string) $this->apsisCoreHelper->getStoreNameFromId()
        ];
        return $data;
    }

    /**
     * @return bool
     */
    private function isOkToProceed()
    {
        $store = $this->apsisCoreHelper->getStore();
        $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());

        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_LOGIN
        );

        $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED
        );

        return ($account && $event && $sync) ? true : false;
    }
}
