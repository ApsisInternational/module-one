<?php

namespace Apsis\One\Model;

use Apsis\One\Model\Sql\ExpressionFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Apsis\One\Model\Service\Log;

/**
 * Class Profile
 *
 * @method string getIntegrationUid()
 * @method $this setIntegrationUid(string $value)
 * @method int getSubscriberStatus()
 * @method $this setSubscriberStatus(int $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method int getSubscriberStoreId()
 * @method $this setSubscriberStoreId(int $value)
 * @method int getSubscriberId()
 * @method $this setSubscriberId(int $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method string getEmail()
 * @method $this setEmail(string $value)
 * @method int getSubscriberSyncStatus()
 * @method $this setSubscriberSyncStatus(int $value)
 * @method int getCustomerSyncStatus()
 * @method $this setCustomerSyncStatus(int $value)
 * @method int getIsSubscriber()
 * @method $this setIsSubscriber(int $value)
 * @method int getIsCustomer()
 * @method $this setIsCustomer(int $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */
class Profile extends AbstractModel
{
    const SYNC_STATUS_PENDING = 0;
    const SYNC_STATUS_BATCHED = 1;
    const SYNC_STATUS_SYNCED = 2;
    const SYNC_STATUS_FAILED = 3;
    const SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE = 4;
    const SYNC_STATUS_NA = 5;

    const STATUS_TEXT_MAP = [
        self::SYNC_STATUS_PENDING => 'Pending',
        self::SYNC_STATUS_BATCHED => 'Batched',
        self::SYNC_STATUS_SYNCED => 'Synced',
        self::SYNC_STATUS_FAILED => 'Failed',
        self::SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE => 'Pending Update',
        self::SYNC_STATUS_NA => 'N/A',
    ];

    const IS_FLAGGED = 1;
    const NO_FLAGGED = 0;

    const PROFILE_TYPE_CUSTOMER = 'customer';
    const PROFILE_TYPE_SUBSCRIBER = 'subscriber';

    const PROFILE_TYPE_TEXT_MAP = [
        ProfileBatch::BATCH_TYPE_CUSTOMER => self::PROFILE_TYPE_CUSTOMER,
        ProfileBatch::BATCH_TYPE_SUBSCRIBER => self::PROFILE_TYPE_SUBSCRIBER
    ];

    const INTEGRATION_KEYSPACE = 'integration_uid';
    const EMAIL_FIELD = 'email';
    const EMAIL_KEYSPACE_DISCRIMINATOR = 'com.apsis1.keyspaces.email';
    const EMAIL_CHANNEL_DISCRIMINATOR = 'com.apsis1.channels.email';

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ExpressionFactory
     */
    private $expressionFactory;

    /**
     * @var Log
     */
    private $logger;

    /**
     * Subscriber constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param Log $logger
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        Log $logger,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->expressionFactory = $expressionFactory;
        $this->dateTime = $dateTime;
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
        $this->_init(ProfileResource::class);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        $this->logger->debug(__METHOD__, ['Entity Id' => $this->getId()]);
        return parent::afterDelete();
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        $this->setUpdatedAt($this->dateTime->formatDate(true));

        if ($this->isObjectNew()) {
            $this->setIntegrationUid(
                $this->expressionFactory->create(
                    ["expression" => "(SELECT UUID())"]
                )
            );
        }

        return $this;
    }
}
