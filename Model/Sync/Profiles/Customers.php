<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ProfileBatch;
use Apsis\One\Model\ProfileBatchFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\Collection;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\File as ApsisFileHelper;
use Apsis\One\Model\Sync\Profiles\Customers\CustomerFactory as CustomerDataFactory;
use Magento\Customer\Model\Customer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Customers implements ProfileSyncInterface
{
    const LIMIT = 1000;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var ApsisConfigHelper
     */
    private ApsisConfigHelper $apsisConfigHelper;

    /**
     * @var ApsisFileHelper
     */
    private ApsisFileHelper $apsisFileHelper;

    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * @var CustomerDataFactory
     */
    private CustomerDataFactory $customerDataFactory;

    /**
     * @var ProfileBatchFactory
     */
    private ProfileBatchFactory $profileBatchFactory;

    /**
     * @var string
     */
    private string $keySpace;

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
     * @inheritdoc
     */
    public function processForStore(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper): void
    {
        try {
            $this->apsisCoreHelper = $apsisCoreHelper;

            $section = $this->apsisCoreHelper->getStoreConfig($store, ApsisConfigHelper::MAPPINGS_SECTION_SECTION);
            $mappings = $this->apsisConfigHelper->getCustomerAttributeMapping($store);
            $this->keySpace = $this->apsisCoreHelper->getKeySpaceDiscriminator($section);
            // Validate all things compulsory
            if (! $section || empty($mappings) || ! isset($mappings['email']) || ! $this->keySpace) {
                return;
            }

            $limit = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::PROFILE_SYNC_CUSTOMER_BATCH_SIZE
            );
            if (empty($limit)) {
                $limit = self::LIMIT;
            }

            $collection = $this->profileCollectionFactory->create()->getCustomerToBatchByStore($store->getId(), $limit);
            if (! $collection->getSize()) {
                return;
            }

            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
            if (! $client) {
                return;
            }

            $attributesArrWithVersionId = $this->apsisCoreHelper->getAttributeVersionIds($client, $section);
            if (empty($attributesArrWithVersionId)) {
                return;
            }

            $this->batchCustomersForStore($store, $collection, $mappings, $attributesArrWithVersionId);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param array $salesData
     * @param Customer $customer
     *
     * @return Customer
     */
    private function setSalesDataOnCustomer(array $salesData, Customer $customer): Customer
    {
        try {
            foreach ($salesData as $column => $value) {
                try {
                    $customer->setData($column, $value);
                } catch (Throwable $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->log(__METHOD__ . ' Skipped for Customer id: ' . $customer->getId());

                    continue;
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $customer;
    }

    /**
     * @param StoreInterface $store
     * @param Collection $collection
     * @param array $mappings
     * @param array $attributesArrWithVersionId
     *
     * @return void
     */
    private function batchCustomersForStore(
        StoreInterface $store,
        Collection $collection,
        array $mappings,
        array $attributesArrWithVersionId
    ): void {
        try {
            $integrationIdsArray = $this->getIntegrationIdsArray($collection);
            $jsonMappings = $this->apsisConfigHelper->getJsonMappingData(
                $this->keySpace,
                $mappings,
                $attributesArrWithVersionId
            );
            $mappings = array_merge([Profile::INTEGRATION_KEYSPACE => Profile::INTEGRATION_KEYSPACE], $mappings);

            $file = $this->createFileWithHeaders($store, array_keys($mappings));
            if (empty($file)) {
                $info = ['Message' => 'Unable to create file', 'Store Id' => $store->getId()];
                $this->apsisCoreHelper->debug(__METHOD__, $info);

                return;
            }

            $customerIds = $collection->getColumnValues('customer_id');
            $customerCollection = $this->profileResource->buildCustomerCollection(
                (int) $store->getId(),
                $customerIds,
                $this->apsisCoreHelper
            );
            if (empty($customerCollection)) {
                return;
            }

            $salesData = $this->profileResource->getSalesDataForCustomers($store, $customerIds, $this->apsisCoreHelper);
            $customersToUpdate = [];

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
                } catch (Throwable $e) {
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

                $info = [
                    'Total Profiles Batched' => count($customersToUpdate),
                    'Store Id' => $store->getId()
                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            if (! empty($customersToUpdate)) {
                $this->apsisCoreHelper->log(__METHOD__ . ': Skipped customers with id :' .
                    implode(',', $customersToUpdate));
            }
        }
    }

    /**
     * @param StoreInterface $store
     * @param array $headers
     *
     * @return string
     */
    private function createFileWithHeaders(StoreInterface $store, array $headers): string
    {
        try {
            $file = strtolower($store->getCode() . '_customer_' . date('d_m_Y_His') . '.csv');
            $this->apsisFileHelper->outputCSV($file, $headers);

            return $file;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param Collection $collection
     *
     * @return array
     */
    private function getIntegrationIdsArray(Collection $collection): array
    {
        $integrationIdsArray = [];

        try {
            foreach ($collection as $item) {
                try {
                    $integrationIdsArray[$item->getCustomerId()] = $item->getIntegrationUid();
                } catch (Throwable $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->log(__METHOD__ . ' Skipped for Profile id: ' . $item->getId());

                    continue;
                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $integrationIdsArray;
    }
}
