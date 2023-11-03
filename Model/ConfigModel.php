<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\ConfigResource;
use Apsis\One\Service\Sub\SubConfigServiceFactory;
use Apsis\One\Service\Sub\SubConfigService;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;

/**
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getSectionDiscriminator()
 * @method $this setSectionDiscriminator(string $value)
 * @method string getIntegrationConfig()
 * @method $this setIntegrationConfig(string $value)
 * @method string getApiToken()
 * @method $this setApiToken(string $value)
 * @method string getApiTokenExpiry()
 * @method $this setApiTokenExpiry(string $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 *
 */
class ConfigModel extends AbstractModel
{
    const RESOURCE_MODEL = ConfigResource::class;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @var SubConfigServiceFactory
     */
    private SubConfigServiceFactory $subConfigServiceFactory;

    /**
     * @var SubConfigService
     */
    protected SubConfigService $apiConfig;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param EncryptorInterface $encryptor
     * @param SubConfigServiceFactory $subConfigServiceFactory
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
        SubConfigServiceFactory $subConfigServiceFactory,
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
        $this->subConfigServiceFactory = $subConfigServiceFactory;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheirtDoc
     */
    public function beforeSave(): static
    {
        $config = json_decode($this->getIntegrationConfig(), true);
        if (isset($config['one_api_key']['client_secret'])) {
            $config['one_api_key']['client_secret'] =
                $this->encryptor->encrypt($config['one_api_key']['client_secret']);
        }
        $this->setIntegrationConfig(json_encode($config));
        if ($this->getApiToken()) {
            $this->setApiToken($this->encryptor->encrypt($this->getApiToken()));
        }
        return parent::beforeSave();
    }

    /**
     * @inheirtDoc
     */
    public function afterLoad(): ConfigModel
    {
        parent::afterLoad();
        $config = json_decode($this->getIntegrationConfig(), true);
        if (isset($config['one_api_key']['client_secret'])) {
            $config['one_api_key']['client_secret'] =
                $this->encryptor->decrypt($config['one_api_key']['client_secret']);
        }
        $this->setIntegrationConfig(json_encode($config));
        if ($this->getApiToken()) {
            $this->setApiToken($this->encryptor->decrypt($this->getApiToken()));
        }
        $this->setApiConfig();
        return $this;
    }

    /**
     * @return $this
     */
    protected function setApiConfig(): ConfigModel
    {
        $this->apiConfig = $this->subConfigServiceFactory->create()
            ->setConfig(json_decode($this->getIntegrationConfig(), true));
        return $this;
    }

    /**
     * @return SubConfigService|null
     */
    public function getApiConfig(): ?SubConfigService
    {
        return $this->apiConfig ?? null;
    }
}
