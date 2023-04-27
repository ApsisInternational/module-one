<?php

namespace Apsis\One\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Queue as QueueResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Apsis\One\Model\Service\Core;

/**
 * Class Webhook
 *
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getSubscriptionId()
 * @method $this setSubscriptionId(string $value)
 * @method int getType()
 * @method $this setType(int $value)
 * @method string getCallbackUrl()
 * @method $this setCallbackUrl(string $value)
 * @method string getFields()
 * @method $this setFields(string $value)
 * @method string getSecret()
 * @method $this setSecret(string $value)
 * @method string getBackoffConfig()
 * @method $this setBackoffConfig(string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 *
 */
class Webhook extends AbstractModel
{
    const TYPE_RECORD = 1;
    const TYPE_CONSENT = 2;
    const TYPE_TEXT_MAP = [
        self::TYPE_RECORD => 'Record',
        self::TYPE_CONSENT => 'Consent'
    ];

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * Webhook constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param EncryptorInterface $encryptor
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        EncryptorInterface $encryptor,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->dateTime = $dateTime;
        $this->encryptor = $encryptor;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @inheirtDoc
     */
    public function _construct()
    {
        $this->_init(QueueResource::class);
    }

    /**
     * @inheirtDoc
     */
    public function beforeSave()
    {
        parent::beforeSave();
        $now = $this->dateTime->formatDate(true);
        if ($this->isObjectNew()) {
            $this->setCreatedAt($now)
                ->setSubscriptionId(Core::generateUniversallyUniqueIdentifier());
        }
        $this->setUpdatedAt($now)
            ->setSecret($this->encryptor->encrypt($this->getSecret()));
        return $this;
    }

    /**
     * @inheirtDoc
     */
    public function afterLoad()
    {
        parent::afterLoad();
        $this->setSecret($this->encryptor->decrypt($this->getSecret()));
        return $this;
    }
}
