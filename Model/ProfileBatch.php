<?php

namespace Apsis\One\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\ProfileBatch as ProfileBatchResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Apsis\One\Model\ResourceModel\ProfileBatch\CollectionFactory as ProfileBatchCollectionFactory;
use Apsis\One\Model\ResourceModel\ProfileBatch\Collection as ProfileBatchCollection;

/**
 * Class ProfileBatch
 *
 * @method string getImportId()
 * @method $this setImportId(string $value)
 * @method string getFileUploadExpiresAt()
 * @method $this setFileUploadExpiresAt(string $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getFilePath()
 * @method $this setFilePath(string $value)
 * @method string getJsonMappings()
 * @method $this setJsonMappings(string $value)
 * @method int getBatchType()
 * @method $this setBatchType(int $value)
 * @method string getEntityIds()
 * @method $this setEntityIds(string $value)
 * @method int getSyncStatus()
 * @method $this setSyncStatus(int $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */
class ProfileBatch extends AbstractModel
{
    const BATCH_TYPE_SUBSCRIBER = 1;
    const BATCH_TYPE_CUSTOMER = 2;

    const SYNC_STATUS_PENDING = 0;
    const SYNC_STATUS_PROCESSING = 1;
    const SYNC_STATUS_COMPLETED = 2;
    const SYNC_STATUS_FAILED = 3;
    const SYNC_STATUS_ERROR = 4;

    const PROCESSING_LIMIT = 20;
    const PENDING_LIMIT = 2;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ProfileBatchResource
     */
    private $profileBatchResource;

    /**
     * @var ProfileBatchCollectionFactory
     */
    private $profileBatchCollectionFactory;

    /**
     * Subscriber constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ProfileBatchResource $profileBatchResource
     * @param ProfileBatchCollectionFactory $profileBatchCollectionFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ProfileBatchResource $profileBatchResource,
        ProfileBatchCollectionFactory $profileBatchCollectionFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->profileBatchCollectionFactory = $profileBatchCollectionFactory;
        $this->dateTime = $dateTime;
        $this->profileBatchResource = $profileBatchResource;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Constructor
     */
    public function _construct()
    {
        $this->_init(ProfileBatchResource::class);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        $this->setUpdatedAt($this->dateTime->formatDate(true));
        return $this;
    }

    /**
     * @param int $storeId
     * @param string $filePath
     * @param int $type
     * @param string $ids
     * @param string $jsonMappings
     *
     * @throws AlreadyExistsException
     */
    public function registerBatchItem(int $storeId, string $filePath, int $type, string $ids, string $jsonMappings)
    {
        $this->setStoreId($storeId)
            ->setFilePath($filePath)
            ->setJsonMappings($jsonMappings)
            ->setBatchType($type)
            ->setEntityIds($ids)
            ->setSyncStatus(self::SYNC_STATUS_PENDING);
        $this->profileBatchResource->save($this);
    }

    /**
     * @param int $storeId
     *
     * @return ProfileBatchCollection
     */
    public function getPendingBatchItemsForStore(int $storeId)
    {
        return $this->getBatchItemCollectionForStoreByStatus(
            $storeId,
            self::SYNC_STATUS_PENDING,
            self::PENDING_LIMIT
        );
    }

    /**
     * @param int $storeId
     *
     * @return ProfileBatchCollection
     */
    public function getProcessingBatchItemsForStore(int $storeId)
    {
        return $this->getBatchItemCollectionForStoreByStatus(
            $storeId,
            self::SYNC_STATUS_PROCESSING,
            self::PROCESSING_LIMIT
        );
    }

    /**
     * @param int $storeId
     * @param int $status
     * @param int $limit
     *
     * @return ProfileBatchCollection
     */
    private function getBatchItemCollectionForStoreByStatus(int $storeId, int $status, int $limit)
    {
        return $this->profileBatchCollectionFactory->create()
            ->addFieldToFilter('sync_status', $status)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize($limit);
    }
}
