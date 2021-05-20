<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Profile\Collection;
use Exception;
use Magento\Customer\Model\Customer;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\File as ApsisFileHelper;
use Apsis\One\Model\Sync\Profiles\Customers\CustomerFactory as CustomerDataFactory;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ProfileBatchFactory;
use Apsis\One\Model\ProfileBatch;
use Magento\Store\Model\ScopeInterface;

class Customers implements ProfileSyncInterface
{
    const LIMIT = 1000;

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
     * @var ProfileBatchFactory
     */
    private $profileBatchFactory;

    /**
     * @var string
     */
    private $keySpaceDiscriminator;

    /**
     * Customers constructor.
     *
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param ApsisConfigHelper $apsisConfigHelper
     * @param ApsisFileHelper $apsisFileHelper
     * @param CustomerDataFactory $customerDataFactory
     * @param ProfileBatchFactory $profileBatchFactory
     */
    public function __construct(
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        ApsisConfigHelper $apsisConfigHelper,
        ApsisFileHelper $apsisFileHelper,
        CustomerDataFactory $customerDataFactory,
        ProfileBatchFactory $profileBatchFactory
    ) {
        $this->customerDataFactory = $customerDataFactory;
        $this->apsisFileHelper = $apsisFileHelper;
        $this->apsisConfigHelper = $apsisConfigHelper;
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->profileBatchFactory = $profileBatchFactory;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function processForStore(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $this->apsisCoreHelper = $apsisCoreHelper;
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED
            );
            $mappings = $this->apsisConfigHelper->getCustomerAttributeMapping($store);
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());

            if ($client && $sectionDiscriminator && $sync && ! empty($mappings) && isset($mappings['email'])) {
                $attributesArrWithVersionId = $this->apsisCoreHelper
                    ->getAttributesArrWithVersionId($client, $sectionDiscriminator);
                $this->keySpaceDiscriminator = $this->apsisCoreHelper
                    ->getKeySpaceDiscriminator($sectionDiscriminator);
                $limit = $this->apsisCoreHelper->getStoreConfig(
                    $store,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_CUSTOMER_BATCH_SIZE
                );
                $collection = $this->profileCollectionFactory->create()
                    ->getCustomerToBatchByStore($store->getId(), ($limit) ? $limit : self::LIMIT);

                if ($collection->getSize() && ! empty($attributesArrWithVersionId)) {
                    $this->batchCustomersForStore($store, $collection, $mappings, $attributesArrWithVersionId);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array $salesData
     * @param Customer $customer
     *
     * @return Customer
     */
    private function setSalesDataOnCustomer(array $salesData, Customer $customer)
    {
        try {
            foreach ($salesData as $column => $value) {
                try {
                    $customer->setData($column, $value);
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->log(__METHOD__ . ' Skipped for Customer id: ' . $customer->getId());
                    continue;
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $customer;
    }

    /**
     * @param StoreInterface $store
     * @param Collection $collection
     * @param array $mappings
     * @param array $attributesArrWithVersionId
     */
    private function batchCustomersForStore(
        StoreInterface $store,
        Collection $collection,
        array $mappings,
        array $attributesArrWithVersionId
    ) {
        try {
            $integrationIdsArray = $this->getIntegrationIdsArray($collection);
            $file = strtolower($store->getCode() . '_customer_' . date('d_m_Y_His') . '.csv');

            $jsonMappings = $this->apsisConfigHelper->getJsonMappingData(
                $this->keySpaceDiscriminator,
                $mappings,
                $attributesArrWithVersionId
            );

            $mappings = array_merge([Profile::INTEGRATION_KEYSPACE => Profile::INTEGRATION_KEYSPACE], $mappings);
            $this->apsisFileHelper->outputCSV($file, array_keys($mappings));
            $customerIds = $collection->getColumnValues('customer_id');
            $customerCollection = $this->profileResource->buildCustomerCollection((int) $store->getId(), $customerIds);
            $salesData = $this->profileResource->getSalesDataForCustomers($store, $customerIds, $this->apsisCoreHelper);
            $customersToUpdate = [];

            /** @var Customer $customer */
            foreach ($customerCollection as $customer) {
                try {
                    if (isset($integrationIdsArray[$customer->getId()])) {
                        $customer->setIntegrationUid($integrationIdsArray[$customer->getId()]);
                        $customer->setProfileKey($integrationIdsArray[$customer->getId()]);
                        if (isset($salesData[$customer->getId()])) {
                            $customer = $this->setSalesDataOnCustomer($salesData[$customer->getId()], $customer);
                        }
                        $customerData = $this->customerDataFactory->create()
                            ->setModelData(array_keys($mappings), $customer, $this->apsisCoreHelper)
                            ->toCSVArray();
                        $this->apsisFileHelper->outputCSV($file, $customerData);
                        $customersToUpdate[] = $customer->getId();
                    }
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->log(__METHOD__ . ': Skipped customer with id :' . $customer->getId());
                    continue;
                }
                $customer->clearInstance();
            }

            if (! empty($customersToUpdate)) {
                $filePath = $this->apsisFileHelper->getFilePath($file);
                $this->profileBatchFactory->create()
                    ->registerBatchItem(
                        $store->getId(),
                        $filePath,
                        ProfileBatch::BATCH_TYPE_CUSTOMER,
                        implode(',', $customersToUpdate),
                        $this->apsisCoreHelper->serialize($jsonMappings)
                    );
                $this->profileResource->updateCustomerSyncStatus(
                    $customersToUpdate,
                    Profile::SYNC_STATUS_BATCHED,
                    $this->apsisCoreHelper
                );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            if (! empty($customersToUpdate)) {
                $this->apsisCoreHelper->log(__METHOD__ . ': Skipped customers with id :' .
                    implode(',', $customersToUpdate));
            }
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
        try {
            /** @var Profile $item */
            foreach ($collection as $item) {
                try {
                    $integrationIdsArray[$item->getCustomerId()] = $item->getIntegrationUid();
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->log(__METHOD__ . ' Skipped for Profile id: ' . $item->getId());
                    continue;
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $integrationIdsArray;
    }
}
