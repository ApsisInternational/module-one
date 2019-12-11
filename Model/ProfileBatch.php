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
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\ProfileBatch\CollectionFactory as ProfileBatchCollectionFactory;
use Apsis\One\Model\ResourceModel\ProfileBatch\Collection as ProfileBatchCollection;

class ProfileBatch extends AbstractModel
{
    const BATCH_TYPE_SUBSCRIBER = 1;
    const BATCH_TYPE_CUSTOMER = 2;

    const LIMIT = 5;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ProfileBatchResource
     */
    private $profileBatchResource;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

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
     * @param ApsisCoreHelper $apsisCoreHelper
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
        ApsisCoreHelper $apsisCoreHelper,
        ProfileBatchCollectionFactory $profileBatchCollectionFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->profileBatchCollectionFactory = $profileBatchCollectionFactory;
        $this->dateTime = $dateTime;
        $this->profileBatchResource = $profileBatchResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
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
     *
     * @throws AlreadyExistsException
     */
    public function registerBatchItem(int $storeId, string $filePath, int $type, string $ids)
    {
        $this->setStoreId($storeId)
            ->setFilePath($filePath)
            ->setBatchType($type)
            ->setEntityIds($ids)
            ->setSyncStatus(Profile::SYNC_STATUS_PENDING);
        $this->profileBatchResource->save($this);
    }

    /**
     * @param ProfileBatch $item
     * @param int $status
     * @param string $msg
     *
     * @throws AlreadyExistsException
     */
    public function updateItem(ProfileBatch $item, int $status, string $msg = '')
    {
        $item->setSyncStatus($status);
        if (strlen($msg)) {
            $item->setErrorMessage($msg);
        }
        $this->profileBatchResource->save($item);
    }

    /**
     * @param int $storeId
     * @param int $type
     * @return ProfileBatchCollection
     */
    public function getBatchItemCollection(int $storeId, int $type)
    {
        return $this->profileBatchCollectionFactory->create()
            ->addFieldToFilter('batch_type', $type)
            ->addFieldToFilter('sync_status', Profile::SYNC_STATUS_PENDING)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize(self::LIMIT);
    }
}
