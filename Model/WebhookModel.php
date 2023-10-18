<?php

namespace Apsis\One\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Apsis\One\Model\ResourceModel\WebhookResource;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;

/**
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
class WebhookModel extends AbstractModel
{
    const RESOURCE_MODEL = WebhookResource::class;

    const TYPE_RECORD = 1;
    const TYPE_CONSENT = 2;
    const TYPE_TEXT_MAP = [
        self::TYPE_RECORD => 'Record',
        self::TYPE_CONSENT => 'Consent'
    ];

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param EncryptorInterface $encryptor
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        EncryptorInterface $encryptor,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $dateTime,
            $expressionFactory,
            $resource,
            $resourceCollection,
            $data
        );
        $this->dateTime = $dateTime;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheirtDoc
     */
    public function beforeSave(): static
    {
        $this->setSecret($this->encryptor->encrypt($this->getSecret()));
        return parent::beforeSave();
    }

    /**
     * @inheirtDoc
     */
    public function afterLoad(): WebhookModel
    {
        parent::afterLoad();
        $this->setSecret($this->encryptor->decrypt($this->getSecret()));
        return $this;
    }
}
