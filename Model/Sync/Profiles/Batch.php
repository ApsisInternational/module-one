<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\ApiClient\Client;
use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ProfileBatch;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\ProfileBatch as ProfileBatchResource;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use \Exception;
use Apsis\One\Model\ProfileBatchFactory;

class Batch
{
    /**
     * @var ProfileBatchFactory
     */
    private $profileBatchFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var ProfileBatchResource
     */
    private $profileBatchResource;

    /**
     * Batch constructor.
     *
     * @param ProfileBatchFactory $profileBatchFactory
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileResource $profileResource
     * @param ProfileBatchResource $profileBatchResource
     */
    public function __construct(
        ProfileBatchFactory $profileBatchFactory,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileResource $profileResource,
        ProfileBatchResource $profileBatchResource
    ) {
        $this->profileBatchResource = $profileBatchResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->profileBatchFactory = $profileBatchFactory;
        $this->profileResource = $profileResource;
    }

    /**
     * @param StoreInterface $store
     */
    public function syncBatchItemsForStore(StoreInterface $store)
    {
        $apiClient = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
        $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        );
        if ($apiClient && $sectionDiscriminator) {
            $this->handlePendingCollectionForStore($apiClient, $store, $sectionDiscriminator);
            $this->handleProcessingCollectionForStore($apiClient, $store, $sectionDiscriminator);
        }
    }

    /**
     * @param StoreInterface $store
     * @param Client $apiClient
     * @param string $sectionDiscriminator
     */
    private function handlePendingCollectionForStore(
        Client $apiClient,
        StoreInterface $store,
        string $sectionDiscriminator
    ) {
        $collection = $this->profileBatchFactory->create()
            ->getPendingBatchItemsForStore($store->getId());
        if ($collection->getSize()) {
            foreach ($collection as $item) {
                try {
                    $result = $apiClient->initializeProfileImport(
                        $sectionDiscriminator,
                        (array) $this->apsisCoreHelper->unserialize($item->getJsonMappings())
                    );

                    if ($result && isset($result->import_id)) {
                        $apiClient->uploadFileForProfileImport(
                            $result->file_upload_url,
                            (array) $result->file_upload_body,
                            $item->getFilePath()
                        );
                        $item->setImportId($result->import_id)
                            ->setFileUploadExpireAt($result->file_upload_url_expires_at);
                        $this->updateItem($item, ProfileBatch::SYNC_STATUS_PROCESSING);
                    }
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                    $this->apsisCoreHelper->log('Skipped batch item :' . $item->getId());
                    continue;
                }
            }
        }
    }

    /**
     * @param StoreInterface $store
     * @param Client $apiClient
     * @param string $sectionDiscriminator
     */
    private function handleProcessingCollectionForStore(
        Client $apiClient,
        StoreInterface $store,
        string $sectionDiscriminator
    ) {
        $collection = $this->profileBatchFactory->create()
            ->getProcessingBatchItemsForStore($store->getId());
        if ($collection->getSize()) {
            foreach ($collection as $item) {
                try {
                    $result = $apiClient->getImportStatus($sectionDiscriminator, $item->getImportId());
                    if ($result && isset($result->result)) {
                        if ($result->result->status === 'completed') {
                            $this->updateProfilesStatus($store, $item, Profile::SYNC_STATUS_SYNCED);
                            $this->updateItem($item, ProfileBatch::SYNC_STATUS_COMPLETED);
                        } elseif ($result->result->status === 'error') {
                            $this->updateProfilesStatus($store, $item, Profile::SYNC_STATUS_FAILED);
                            $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED);
                        } elseif ($result->result->status === 'waiting_for_file' &&
                            $this->apsisCoreHelper->isExpired($item->getFileUploadExpireAt())
                        ) {
                            $this->updateProfilesStatus($store, $item, Profile::SYNC_STATUS_FAILED);
                            $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED);
                        }
                    }
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                    $this->apsisCoreHelper->log('Skipped batch item :' . $item->getId());
                    continue;
                }
            }
        }
    }

    /**
     * @param ProfileBatch $item
     * @param int $status
     * @param string $msg
     *
     * @throws AlreadyExistsException
     */
    private function updateItem(ProfileBatch $item, int $status, string $msg = '')
    {
        $item->setSyncStatus($status);
        if (strlen($msg)) {
            $item->setErrorMessage($msg);
        }
        $this->profileBatchResource->save($item);
    }

    /**
     * @param StoreInterface $store
     * @param ProfileBatch $item
     * @param int $status
     */
    private function updateProfilesStatus(StoreInterface $store, ProfileBatch $item, int $status)
    {
        if ($item->getBatchType() == ProfileBatch::BATCH_TYPE_CUSTOMER) {
            $this->profileResource->updateCustomerSyncStatus(
                explode(",", $item->getEntityIds()),
                $store->getId(),
                $status
            );
        } elseif ($item->getBatchType() == ProfileBatch::BATCH_TYPE_SUBSCRIBER) {
            $this->profileResource->updateSubscribersSyncStatus(
                explode(",", $item->getEntityIds()),
                $store->getId(),
                $status
            );
        }
    }
}
