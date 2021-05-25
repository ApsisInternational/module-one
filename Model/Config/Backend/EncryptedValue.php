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
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $value = 'Obscured value, encrypted. ';

            if ($this->getPath() == Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET) {
                $groups = $this->request->getPost('groups');

                //If set to inherit parent context's config value then no need to validate
                if (isset($groups['oauth']['fields']['id']['inherit']) ||
                    isset($groups['oauth']['fields']['secret']['inherit'])
                ) {
                    return parent::afterSave();
                }

                $id = $groups['oauth']['fields']['id']['value'] ?? false;
                $secret = $this->processValue($this->getValue());
                $region = $groups['oauth']['fields']['region']['value'] ?? false;

                if (empty($id) || empty($secret) || empty($region)) {
                    //Set registry key
                    $this->_registry->unregister(Value::REGISTRY_NAME_FOR_ERROR);
                    $this->_registry->register(Value::REGISTRY_NAME_FOR_ERROR, true, true);
                }

                $status = $this->isApiCredentialsValid($id, $secret, $region);
                if ($status === self::SUCCESS_MESSAGE) {
                    $this->apsisCoreHelper->log(self::SUCCESS_MESSAGE);
                    $this->messageManager->addSuccessMessage(__(self::SUCCESS_MESSAGE));
                } else {
                    //Set registry key
                    $this->_registry->unregister(Value::REGISTRY_NAME_FOR_ERROR);
                    $this->_registry->register(Value::REGISTRY_NAME_FOR_ERROR, true, true);
                }

                //If api credentials changed and there exist an old value.
                if ($this->getOldValue()) {
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
        }

        return parent::afterSave();
    }

    /**
     * @return bool|string
     */
    private function isApiCredentialsValid(string $id, string $secret, string $region)
    {
        try {
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
                return self::SUCCESS_MESSAGE;
            } else {
                $this->apsisCoreHelper->debug($check);
                $this->messageManager->addWarningMessage(__($check));

                return false;
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addWarningMessage(__('Something went wrong, please check exception logs.'));
            return false;
        }
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

            if (empty($client)) {
                return 'Unable to generate access token. Authorization has been denied for this request.';
            } else {
                $keySpaces = $client->getKeySpaces();
                if (is_object($keySpaces) && isset($keySpaces->items)) {
                    foreach ($keySpaces->items as $item) {
                        if (strpos($item->discriminator, 'magento') !== false) {
                            return true;
                        }
                    }
                }
                return 'API credentials are invalid for this integration. Magento keySpace does not exist.';
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 'Something went wrong, please check exception logs.';
        }
    }
}
