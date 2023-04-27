<?php

namespace Apsis\One\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Queue as QueueResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;

/**
 * Class Queue
 *
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getProfileId()
 * @method $this setProfileId(int $value)
 * @method int getType()
 * @method $this setType(int $value)
 * @method int getSyncStatus()
 * @method $this setSyncStatus(int $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getProcessedAt()
 * @method $this setProcessedAt(string $value)
 *
 */
class Queue extends AbstractModel
{
    const RECORD_CREATED = 1;
    const RECORD_UPDATED = 2;
    const RECORD_DELETED = 3;
    const CONSENT_OPT_IN = 4;
    const CONSENT_OPT_OUT = 5;

    const TYPE_TEXT_MAP = [
        self::RECORD_CREATED => 'RECORD: Created',
        self::RECORD_UPDATED => 'RECORD: Updated',
        self::RECORD_DELETED => 'RECORD: Deleted',
        self::CONSENT_OPT_IN => 'CONSENT: Opt-in',
        self::CONSENT_OPT_OUT => 'CONSENT: Opt-out'
    ];

    const STATUS_EXPIRED = 3;
    const STATUS_TEXT_MAP = [
        self::STATUS_EXPIRED => 'Expired'
    ];

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * Queue constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
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
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(QueueResource::class);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($this->dateTime->formatDate(true));
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function setCurrentDateOnProcessedAt(): static
    {
        $this->setProcessedAt((string) $this->dateTime->formatDate(true));
        return $this;
    }
}
