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
use Exception;

class EncryptedValue extends Encrypted
{
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
        parent::beforeSave();

        if ($this->getPath() == Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET && ! empty($this->getValue())) {
            if ($this->isApiCredentialsValid()) {
                $this->_dataSaveAllowed = true;
            } else {
                //Credentials are invalid, dont save value
                $this->_dataSaveAllowed = false;

                //Set registry key
                $this->_registry->unregister(Value::REGISTRY_NAME_FOR_ERROR);
                $this->_registry->register(Value::REGISTRY_NAME_FOR_ERROR, true, true);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function afterSave()
    {
        $value = 'Obscured value, encrypted. ';

        if ($this->isValueChanged()) {

            //If api credentials changed and there exist an old value.
            if ($this->getPath() == Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET && $this->getOldValue()) {
                //Remove old config, reset Profiles and Events
                $this->profileService->fullResetRequest(__METHOD__);

                //Log request
                $this->apsisCoreHelper->log('User changed API credentials. Sending full reset request.');
                $value .= 'API credentials.';
            }
        }

        $info = [
            'Scope' => $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'Scope Id' => $this->getScopeId(),
            'Config Path' => $this->getPath(),
            'Old Value' => $value,
            'New Value' => $value
        ];
        $this->apsisCoreHelper->debug(__METHOD__, $info);

        return parent::afterSave();
    }

    /**
     * @return bool
     */
    private function isApiCredentialsValid()
    {
        try {
            $groups = $this->request->getPost('groups');

            //If set to inherit parent context's config value then no need to validate
            if (isset($groups['oauth']['fields']['id']['inherit']) ||
                isset($groups['oauth']['fields']['secret']['inherit'])
            ) {
                return true;
            }

            $id = $groups['oauth']['fields']['id']['value'] ?? false;
            $secret = $this->processValue($this->getValue()) ?? false;
            $region = $groups['oauth']['fields']['region']['value'] ?? false;

            //All configs needed to validate api credentials
            if ($id && $secret && $region) {

                //Validate api host is reachable
                try {
                    $this->apsisCoreHelper->validateIsUrlReachable($this->apsisCoreHelper->buildHostName($region));
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $msg = '. Cannot enable account. Host for URL must be whitelisted first.';
                    $this->messageManager->addWarningMessage(__($e->getMessage() . $msg));

                    return false;
                }

                //Validate api credential validity for integration
                $check = $this->validateApiCredentials($id, $secret, $region);
                if ($check === true) {
                    $msg = 'API credentials are valid and Magento KeySpace exist.';
                    $this->apsisCoreHelper->log($msg);
                    $this->messageManager->addSuccessMessage(__($msg));

                    return true;
                } else {
                    $this->apsisCoreHelper->debug($check);
                    $this->messageManager->addWarningMessage(__($check));

                    return false;
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addWarningMessage(__('Something went wrong, please check exception logs.'));
            return false;
        }

        return false;
    }

    /**
     * @param string $id
     * @param string $secret
     * @param string $region
     *
     * @return bool|string
     */
    private function validateApiCredentials(string $id, string $secret, string $region)
    {
        try {
            $client = $this->apsisCoreHelper->getApiClient(
                $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeId(),
                true,
                $region,
                $id,
                $secret
            );

            if ($client) {
                $keySpaces = $client->getKeySpaces();
                if (is_object($keySpaces) && isset($keySpaces->items)) {
                    foreach ($keySpaces->items as $item) {
                        if (strpos($item->discriminator, 'magento') !== false) {
                            return true;
                        }
                    }
                }
                return 'API credentials are invalid for this integration. Magento keySpace does not exist.';
            } else {
                return 'Unable to generate access token. Authorization has been denied for this request.';
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 'Something went wrong, please check exception logs.';
        }
    }
}
