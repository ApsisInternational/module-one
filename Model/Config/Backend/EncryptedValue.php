<?php

namespace Apsis\One\Model\Config\Backend;

use Apsis\One\Model\Service\Config;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile as ProfileService;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class EncryptedValue extends Encrypted
{
    const SUCCESS_MESSAGE = 'API credentials are valid and Magento KeySpace exist.';

    /**
     * @var ProfileService
     */
    private $profileService;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * EncryptedValue constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param EncryptorInterface $encryptor
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileService $profileService
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
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
        ApsisCoreHelper $apsisCoreHelper,
        ProfileService $profileService,
        RequestInterface $request,
        ManagerInterface $messageManager,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->profileService = $profileService;
        $this->request = $request;
        $this->messageManager = $messageManager;
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $encryptor,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        //Account config
        if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {

            //Already validated and success. Let the value be saved in DB
            if ($this->assertRegistryStatus(1)) {
                parent::beforeSave();
                return;
            }

            //No need for validation, observer will remove token config for this context
            $groups = $this->request->getPost('groups');
            if (isset($groups['oauth']['fields']['id']['inherit']) ||
                isset($groups['oauth']['fields']['secret']['inherit'])
            ) {
                parent::beforeSave();
                return;
            }
        }

        //Already validated and failure. Do not let the value saved in DB
        if ($this->assertRegistryStatus(2)) {
            $this->_dataSaveAllowed = false;
            return;
        }

        parent::beforeSave();
    }

    /**
     * @inheritdoc
     */
    public function afterSave()
    {
        //Value changed, determined by the model
        if ($this->isValueChanged()) {

            //Placeholder text for encrypted values, we want to avoid logging encrypted value.
            $value = 'Encrypted value. ';

            //Account config
            if ($this->getPath() == Config::ACCOUNTS_OAUTH_SECRET) {

                //Already validated and success. No need to do it again.
                if ($this->assertRegistryStatus(1, __METHOD__, $value)) {
                    return parent::afterSave();
                }

                //Always Remove old config, reset Profiles and Events
                $this->profileService->resetRequest('Full reset from API credentials change');

                //No need for validation, observer will remove token config for this context
                $groups = $this->request->getPost('groups');
                if (isset($groups['oauth']['fields']['id']['inherit']) ||
                    isset($groups['oauth']['fields']['secret']['inherit'])
                ) {
                    $this->log(__METHOD__, $value);
                    return parent::afterSave();
                }

                //Obtained all required values to validate api
                $id = $groups['oauth']['fields']['id']['value'] ?? false;
                $secret = $this->processValue($this->getValue());
                $region = $groups['oauth']['fields']['region']['value'] ?? false;

                //It's a fail if anyone of these are empty
                if (empty($id) || empty($secret) || empty($region)) {
                    $this->setRegistryStatus(2);
                    return $this;
                }

                //Get scope, if none found then it is default
                $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

                //Determine success or fail
                $msg = $this->apsisCoreHelper->isApiCredentialsValid($id, $secret, $region, $scope, $this->getScopeId());
                if ($msg === self::SUCCESS_MESSAGE) {
                    $this->apsisCoreHelper->log(self::SUCCESS_MESSAGE);
                    $this->messageManager->addSuccessMessage(__(self::SUCCESS_MESSAGE));
                    $this->setRegistryStatus(1);
                } else {
                    $this->messageManager->addWarningMessage(__($msg));
                    $this->setRegistryStatus(2);
                    return $this;
                }

                //If api credentials changed and there exist an old value.
                if ($this->getOldValue()) {
                    //Log request
                    $this->apsisCoreHelper->log('User changed API credentials. Sending full reset request.');
                    $value .= 'API credentials.';
                }
            }

            $this->log(__METHOD__, $value);
        }

        return parent::afterSave();
    }

    /**
     * @param int $check
     * @param string $method
     * @param string $value
     *
     * @return bool
     */
    public function assertRegistryStatus(int $check, string $method = '', string $value = '')
    {
        $assert = $this->_registry->registry(Value::REGISTRY_NAME_FOR_STATUS_CHECK) === $check;
        if ($assert && strlen($method) && strlen($value)) {
            $this->log($method, $value);
        }
        return $assert;
    }

    /**
     * @param int $value
     */
    private function setRegistryStatus(int $value)
    {
        $this->_registry->unregister(Value::REGISTRY_NAME_FOR_STATUS_CHECK);
        $this->_registry->register(Value::REGISTRY_NAME_FOR_STATUS_CHECK, $value, true);
    }

    /**
     * @param string $method
     * @param string $value
     */
    private function log(string $method, string $value)
    {
        $info = [
            'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'Scope Id' => $this->getScopeId(),
            'Config Path' => $this->getPath(),
            'Old Value' => $value,
            'New Value' => $value
        ];
        $this->apsisCoreHelper->debug($method, $info);
    }
}
