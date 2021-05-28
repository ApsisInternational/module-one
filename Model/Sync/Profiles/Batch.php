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
use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use stdClass;

class Batch implements ProfileSyncInterface
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
     * @var ApsisFileHelper
     */
    private $apsisFileHelper;

    /**
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * @var int
     */
    private $importCountInProcessingStatus;

    /**
     * @var array
     */
    private $statusToCheckIfExpired = ['queued', 'in_progress', 'waiting_for_file'];

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
            $this->importCountInProcessingStatus = 0;

            $apiClient = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION
            );

            if ($apiClient && $sectionDiscriminator) {
                $this->handleProcessingCollectionForStore($apiClient, $store, $sectionDiscriminator);
                $this->handlePendingCollectionForStore($apiClient, $store, $sectionDiscriminator);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
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
        $collection = $this->profileBatchFactory
            ->create()
            ->getPendingBatchItemsForStore($store->getId());

        if ($collection->getSize()) {
            foreach ($collection as $item) {
                try {
                    if ($this->importCountInProcessingStatus >= ProfileBatch::PENDING_LIMIT) {
                        return;
                    }

                    $result = $apiClient->initializeProfileImport(
                        $sectionDiscriminator,
                        (array) $this->apsisCoreHelper->unserialize($item->getJsonMappings())
                    );

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
                        } catch (Exception $e) {
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

                        $this->importCountInProcessingStatus += 1;
                        $this->updateItem($item, ProfileBatch::SYNC_STATUS_PROCESSING);
                    }
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->log(__METHOD__ . ': Skipped batch item :' . $item->getId());
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
        $collection = $this->profileBatchFactory
            ->create()
            ->getProcessingBatchItemsForStore($store->getId());

        if ($collection->getSize()) {
            $this->importCountInProcessingStatus = $collection->getSize();

            foreach ($collection as $item) {
                try {
                    $result = $apiClient->getImportStatus($sectionDiscriminator, $item->getImportId());
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
                        $this->processImportStatus($store, $result, $item);
                    }
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->log(__METHOD__ . ': Skipped batch item :' . $item->getId());
                    continue;
                }
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        if ($status === ProfileBatch::SYNC_STATUS_FAILED || $status === ProfileBatch::SYNC_STATUS_COMPLETED ||
            $status === ProfileBatch::SYNC_STATUS_ERROR) {
            try {
                $this->apsisFileHelper->deleteFile($item->getFilePath());
            } catch (Exception $e) {
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

                //Reset subscriber status to 5, if not a subscriber
                $this->profileResource->updateSubscribersSyncStatus(
                    $ids,
                    Profile::SYNC_STATUS_NA,
                    $this->apsisCoreHelper,
                    '',
                    [],
                    [],
                    ['error_message' => ''],
                    ['condition' => 'is_', 'value' => Profile::NO_FLAGGED]
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param stdClass $result
     * @param ProfileBatch $item
     */
    private function processImportStatus(StoreInterface $store, stdClass $result, ProfileBatch $item)
    {
        try {
            if ($result->result->status === 'completed') {
                $this->updateProfilesStatus($item, Profile::SYNC_STATUS_SYNCED);
                $this->updateItem($item, ProfileBatch::SYNC_STATUS_COMPLETED);
                $this->importCountInProcessingStatus -= 1;
            } elseif ($result->result->status === 'error') {
                $msg = 'Import failed with returned "error" status';
                $this->updateProfilesStatus($item, Profile::SYNC_STATUS_FAILED, $msg);
                $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED, $msg);
                $this->importCountInProcessingStatus -= 1;
            } elseif ($result->result->status === 'waiting_for_file' && $item->getFileUploadExpiresAt() &&
                $this->apsisDateHelper->isExpired($item->getFileUploadExpiresAt())
            ) {
                $msg = 'File upload time expired';
                $this->updateProfilesStatus($item, Profile::SYNC_STATUS_PENDING);
                $this->updateItem($item, ProfileBatch::SYNC_STATUS_FAILED, $msg);
                $this->importCountInProcessingStatus -= 1;
            } elseif (in_array($result->result->status, $this->statusToCheckIfExpired)) {
                $inputDateTime = $this->apsisDateHelper->getFormattedDateTimeWithAddedInterval($item->getUpdatedAt());
                if ($inputDateTime && $this->apsisDateHelper->isExpired($inputDateTime)) {
                    $msg = 'Expired. Stuck in processing state for 1 day';
                    $this->updateItem($item, ProfileBatch::SYNC_STATUS_ERROR, $msg);
                    $this->updateProfilesStatus($item, Profile::SYNC_STATUS_PENDING);
                    $this->importCountInProcessingStatus -= 1;
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
