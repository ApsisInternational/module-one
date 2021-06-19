<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\ApiClient\Client;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ProfileBatch;
use Apsis\One\Model\ProfileBatchFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\ProfileBatch as ProfileBatchResource;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Service\File as ApsisFileHelper;
use Throwable;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use stdClass;

class Batch implements ProfileSyncInterface
{
    const STATUS_QUEUED = 'queued';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_WAITING_FILE = 'waiting_for_file';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ERROR = 'error';

    const STATUS_TO_CHECK_IF_EXPIRED = [self::STATUS_QUEUED, self::STATUS_IN_PROGRESS, self::STATUS_WAITING_FILE];

    const MSG_STUCK_EXPIRED = 'Expired, stuck in processing state for 1 day';
    const MSG_EXPIRED = 'File upload time expired';
    const MSG_ERROR_FAILED = 'Import failed with returned "error" status';

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
     * @var ApsisFileHelper
     */
    private $apsisFileHelper;

    /**
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * Batch constructor.
     *
     * @param ProfileBatchFactory $profileBatchFactory
     * @param ProfileResource $profileResource
     * @param ProfileBatchResource $profileBatchResource
     * @param ApsisFileHelper $apsisFileHelper
     * @param ApsisDateHelper $apsisDateHelper
     */
    public function __construct(
        ProfileBatchFactory $profileBatchFactory,
        ProfileResource $profileResource,
        ProfileBatchResource $profileBatchResource,
        ApsisFileHelper $apsisFileHelper,
        ApsisDateHelper $apsisDateHelper
    ) {
        $this->apsisDateHelper = $apsisDateHelper;
        $this->apsisFileHelper = $apsisFileHelper;
        $this->profileBatchResource = $profileBatchResource;
        $this->profileBatchFactory = $profileBatchFactory;
        $this->profileResource = $profileResource;
    }

    /**
     * @inheritdoc
     */
    public function processForStore(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $this->apsisCoreHelper = $apsisCoreHelper;

            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION
            );

            // Validate all things compulsory
            if (! $sectionDiscriminator) {
                return;
            }

            $this->handleProcessingCollectionForStore($store, $sectionDiscriminator);
            $this->handlePendingCollectionForStore($store, $sectionDiscriminator);

        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $section
     */
    private function handlePendingCollectionForStore(StoreInterface $store, string $section)
    {
        $collection = $this->profileBatchFactory
            ->create()
            ->getPendingBatchItemsForStore($store->getId());
        if (! $collection->getSize()) {
            return;
        }


        $apiClient = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
        if (! $apiClient) {
            return;
        }

        foreach ($collection as $item) {
            try {
                if ($apiClient->getImportCountInProcessingStatus() >= Client::MAX_PRE_FILE_IMPORT_API_LIMIT) {
                    return;
                }

                $jsonMappings = (array) $this->apsisCoreHelper->unserialize($item->getJsonMappings());
                $result = $apiClient->initializeProfileImport($section, $jsonMappings);

                if ($result === false || $result === null) {
                    $this->apsisCoreHelper->log(
                        __METHOD__ . ': Unable to initialise import for Store ' . $store->getCode() .
                        ' Item ' . $item->getId() . '. Integration will try again in next cron run.'
                    );

                    continue;

                } elseif (is_string($result)) {
                    $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED, $result);
                    $this->updateProfilesStatus(
                        $item,
                        Profile::SYNC_STATUS_FAILED,
                        'Batch id: ' . $item->getId() . ' - ' . $result
                    );

                    continue;
                }

                if ($result && isset($result->import_id)) {
                    try {
                        $this->apsisCoreHelper->validateIsUrlReachable($result->file_upload_url);
                    } catch (Throwable $e) {
                        $this->apsisCoreHelper->logError(__METHOD__, $e);

                        $this->apsisCoreHelper->disableProfileSync(ScopeInterface::SCOPE_STORES, $store->getId());
                        return;
                    }

                    $item->setImportId($result->import_id)
                        ->setFileUploadExpiresAt($result->file_upload_url_expires_at);

                    $status = $apiClient->uploadFileForProfileImport(
                        $result->file_upload_url,
                        (array) $result->file_upload_body,
                        $item->getFilePath()
                    );

                    if ($status === false) {
                        $this->apsisCoreHelper->log(
                            __METHOD__ . ': Unable to upload file for Store ' . $store->getCode() .
                            ' Item ' . $item->getId()
                        );

                        continue;
                    } elseif (is_string($status)) {
                        $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED, $status);
                        $this->updateProfilesStatus(
                            $item,
                            Profile::SYNC_STATUS_FAILED,
                            'Batch id: ' . $item->getId() . ' - ' . $result
                        );

                        continue;
                    }

                    $apiClient->countImportCountInProcessingStatus();
                    $this->updateItem($item, ProfileBatch::SYNC_STATUS_PROCESSING);
                }
            } catch (Throwable $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e);
                $this->apsisCoreHelper->log(__METHOD__ . ': Skipped batch item :' . $item->getId());
                continue;
            }
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $section
     */
    private function handleProcessingCollectionForStore(StoreInterface $store, string $section)
    {
        $collection = $this->profileBatchFactory
            ->create()
            ->getProcessingBatchItemsForStore($store->getId());
        if (! $collection->getSize()) {
            return;
        }


        $apiClient = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
        if (! $apiClient) {
            return;
        }

        $apiClient->setImportCountInProcessingStatus($collection->getSize());
        foreach ($collection as $item) {
            try {
                $result = $apiClient->getImportStatus($section, $item->getImportId());
                if ($result === false) {
                    $this->apsisCoreHelper->log(
                        __METHOD__ . ': Unable to get import status for Store ' . $store->getCode() .
                        ' Item ' . $item->getId()
                    );

                    continue;
                }

                if (is_string($result)) {
                    $this->updateItem($item, ProfileBatch::SYNC_STATUS_ERROR, $result);
                    $this->updateProfilesStatus(
                        $item,
                        Profile::SYNC_STATUS_FAILED,
                        'Batch id: ' . $item->getId() . ' - ' . $result
                    );

                    continue;
                }

                if ($result && isset($result->result)) {
                    $this->processImportStatus($apiClient, $result, $item);
                }
            } catch (Throwable $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e);
                $this->apsisCoreHelper->log(__METHOD__ . ': Skipped batch item :' . $item->getId());
                continue;
            }
        }
    }

    /**
     * @param ProfileBatch $item
     * @param int $status
     * @param string $msg
     */
    private function updateItem(ProfileBatch $item, int $status, string $msg = '')
    {
        $item->setSyncStatus($status);
        if (strlen($msg)) {
            $item->setErrorMessage($msg);
        }

        try {
            $this->profileBatchResource->save($item);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        if ($status === ProfileBatch::SYNC_STATUS_FAILED || $status === ProfileBatch::SYNC_STATUS_COMPLETED ||
            $status === ProfileBatch::SYNC_STATUS_ERROR) {
            try {
                $this->apsisFileHelper->deleteFile($item->getFilePath());
            } catch (Throwable $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e);
            }
        }
    }

    /**
     * @param ProfileBatch $item
     * @param int $status
     * @param string $msg
     */
    private function updateProfilesStatus(ProfileBatch $item, int $status, string $msg = '')
    {
        try {
            $ids = explode(",", $item->getEntityIds());
            if ((int)$item->getBatchType() === ProfileBatch::BATCH_TYPE_CUSTOMER) {
                $this->profileResource->updateCustomerSyncStatus(
                    $ids,
                    $status,
                    $this->apsisCoreHelper,
                    $msg
                );
            } elseif ((int)$item->getBatchType() === ProfileBatch::BATCH_TYPE_SUBSCRIBER) {
                $this->profileResource->updateSubscribersSyncStatus(
                    $ids,
                    $status,
                    $this->apsisCoreHelper,
                    $msg
                );
            }

            $info = [
                'Total Profiles Updated' => count($ids),
                'Profile Type' => Profile::PROFILE_TYPE_TEXT_MAP[$item->getBatchType()],
                'Entity Ids' => $item->getEntityIds(),
                'With Status' => Profile::STATUS_TEXT_MAP[$status],
                'Store Id' => $item->getStoreId()
            ];
            $this->apsisCoreHelper->debug(__METHOD__, $info);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Client $apiClient
     * @param stdClass $result
     * @param ProfileBatch $item
     */
    private function processImportStatus(Client $apiClient, stdClass $result, ProfileBatch $item)
    {
        try {
            if ($result->result->status === self::STATUS_COMPLETED) {

                $this->updateProfilesStatus($item, Profile::SYNC_STATUS_SYNCED);
                $this->updateItem($item, ProfileBatch::SYNC_STATUS_COMPLETED);
                $apiClient->countImportCountInProcessingStatus(false);

            } elseif ($result->result->status === self::STATUS_ERROR) {

                $this->updateProfilesStatus($item, Profile::SYNC_STATUS_FAILED, self::MSG_ERROR_FAILED);
                $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED, self::MSG_ERROR_FAILED);
                $apiClient->countImportCountInProcessingStatus(false);

            } elseif ($result->result->status === self::STATUS_WAITING_FILE &&
                $item->getFileUploadExpiresAt() &&
                $this->apsisDateHelper->isExpired($item->getFileUploadExpiresAt())
            ) {

                $this->updateProfilesStatus($item, Profile::SYNC_STATUS_PENDING);
                $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED, self::MSG_EXPIRED);
                $apiClient->countImportCountInProcessingStatus(false);

            } elseif (in_array($result->result->status, self::STATUS_TO_CHECK_IF_EXPIRED)) {

                $inputDateTime = $this->apsisDateHelper->getFormattedDateTimeWithAddedInterval($item->getUpdatedAt());
                if ($inputDateTime && $this->apsisDateHelper->isExpired($inputDateTime)) {

                    $this->updateItem($item, ProfileBatch::SYNC_STATUS_ERROR, self::MSG_STUCK_EXPIRED);
                    $this->updateProfilesStatus($item, Profile::SYNC_STATUS_PENDING);
                    $apiClient->countImportCountInProcessingStatus(false);

                }
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
