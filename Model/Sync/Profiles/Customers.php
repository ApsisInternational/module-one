<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Profile\Collection;
use \Exception;
use Magento\Customer\Model\Customer;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Helper\File as ApsisFileHelper;
use Apsis\One\Model\Sync\Profiles\Customers\CustomerFactory as CustomerDataFactory;
use Apsis\One\Model\Profile;
use Apsis\One\Model\Sync\Profiles;

class Customers
{
    const LIMIT = 500;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ApsisConfigHelper
     */
    private $apsisConfigHelper;

    /**
     * @var ApsisFileHelper
     */
    private $apsisFileHelper;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var CustomerDataFactory
     */
    private $customerDataFactory;

    /**
     * Customers constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param ApsisConfigHelper $apsisConfigHelper
     * @param ApsisFileHelper $apsisFileHelper
     * @param CustomerDataFactory $customerDataFactory
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        ApsisConfigHelper $apsisConfigHelper,
        ApsisFileHelper $apsisFileHelper,
        CustomerDataFactory $customerDataFactory
    ) {
        $this->customerDataFactory = $customerDataFactory;
        $this->apsisFileHelper = $apsisFileHelper;
        $this->apsisConfigHelper = $apsisConfigHelper;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
    }

    /**
     * @param StoreInterface $store
     */
    public function sync(StoreInterface $store)
    {
        $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED
        );
        $mappings = $this->apsisConfigHelper->getCustomerAttributeMapping($store);

        if ($sync && ! empty($mappings) && isset($mappings['email'])) {
            $limit = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_CUSTOMER_BATCH_SIZE
            );
            $collection = $this->profileCollectionFactory->create()
                ->getCustomerToSyncByStore($store->getId(), ($limit) ? $limit : self::LIMIT);

            if ($collection->getSize()) {
                $this->syncCustomersForStore($store, $collection, $mappings);
            }
        }
    }

    /**
     * @param array $salesData
     * @param Customer $customer
     *
     * @return Customer
     */
    private function setSalesDataOnCustomer($salesData, $customer)
    {
        foreach ($salesData as $column => $value) {
            $customer->setData($column, $value);
        }
        return $customer;
    }

    /**
     * @param StoreInterface $store
     * @param Collection $collection
     * @param array $mappings
     */
    private function syncCustomersForStore(StoreInterface $store, Collection $collection, array $mappings)
    {
        try {
            $integrationIdsArray = $this->getIntegrationIdsArray($collection);
            $file = strtolower($store->getCode() . '_customer_' . date('d_m_Y_His') . '.csv');
            $mappings = array_merge(Profiles::DEFAULT_HEADERS, $mappings);
            $this->apsisFileHelper->outputCSV(
                $file,
                $mappings
            );
            $customerIds = $collection->getColumnValues('customer_id');
            $customerCollection = $this->profileResource->buildCustomerCollection(
                $store->getId(),
                $customerIds
            );
            $salesData = $this->profileResource->getSalesDataForCustomers(
                $store,
                $customerIds
            );
            $customersToUpdate = [];

            /** @var Customer $customer */
            foreach ($customerCollection as $customer) {
                try {
                    $customer->setIntegrationUid($integrationIdsArray[$customer->getId()]);
                    if (isset($salesData[$customer->getId()])) {
                        $customer = $this->setSalesDataOnCustomer($salesData[$customer->getId()], $customer);
                    }
                    $subscriberData = $this->customerDataFactory->create()
                        ->setCustomerData(array_keys($mappings), $customer)
                        ->toCSVArray();
                    $this->apsisFileHelper->outputCSV(
                        $file,
                        $subscriberData
                    );
                    $customersToUpdate[] = $customer->getId();
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                    $this->apsisCoreHelper->log(
                        'Skipped customer with id :' . $customer->getId()
                    );
                }

                //clear collection and free memory
                $customer->clearInstance();
            }
            $filePath = $this->apsisFileHelper->getFilePath($file);
            $this->apsisCoreHelper->log('Customer file : ' . $filePath);
            /** @ToDo send file to import profile api */

            $updated = $this->profileResource->updateCustomerSyncStatus(
                $customersToUpdate,
                $store->getId(),
                Profile::SYNC_STATUS_SYNCED
            );

            $this->apsisCoreHelper->log('Total customer synced : ' . $updated);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
        }
    }

    /**
     * @param Collection $collection
     *
     * @return array
     */
    private function getIntegrationIdsArray(Collection $collection)
    {
        $integrationIdsArray = [];
        foreach ($collection as $item) {
            $integrationIdsArray[$item->getCustomerId()] = $item->getIntegrationUid();
        }
        return $integrationIdsArray;
    }
}
