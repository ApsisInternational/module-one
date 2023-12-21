<?php

namespace Apsis\One\Model\Adminhtml\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class InvalidateDataSaveEncryptedBackendModel extends Value
{
    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param EncryptorInterface $encryptor
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        EncryptorInterface $encryptor,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->encryptor = $encryptor;
    }

    /**
     * @inheirtDoc
     */
    public function beforeSave(): static
    {
        $this->_dataSaveAllowed = false;
        return $this;
    }

    /**
     * @inheirtDoc
     */
    protected function _afterLoad(): static
    {
        $value = (string) $this->getValue();
        if (! empty($value) && ($decrypted = $this->encryptor->decrypt($value))) {
            $this->setValue($decrypted);
        }
        return $this;
    }
}
