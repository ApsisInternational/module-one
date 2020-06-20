<?php

namespace Apsis\One\Observer\Adminhtml;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Store\Model\ScopeInterface;

class ValidateApi implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * ApiValidate constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Context $context
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Context $context
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->context = $context;
        $this->messageManager = $context->getMessageManager();
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $groups = $this->context->getRequest()->getPost('groups');

        if (isset($groups['oauth']['fields']['id']['inherit'])
            || isset($groups['oauth']['fields']['secret']['inherit'])
        ) {
            $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
            if (in_array($scope['context_scope'], [ScopeInterface::SCOPE_STORES, ScopeInterface::SCOPE_WEBSITES])) {
                $paths = [
                    ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN,
                    ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE
                ];
                foreach ($paths as $path) {
                    $this->apsisCoreHelper->deleteConfigByScope(
                        $path,
                        $scope['context_scope'],
                        $scope['context_scope_id']
                    );
                }
            }
            return $this;
        }

        $id = $groups['oauth']['fields']['id']['value'] ?? false;
        $secret = $groups['oauth']['fields']['secret']['value'] ?? false;

        if ($id && $secret) {
            $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
            if (! $this->isValid($id, $secret, $scope)) {
                $this->apsisCoreHelper->saveConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED,
                    0,
                    $scope['context_scope'],
                    $scope['context_scope_id']
                );
                $this->apsisCoreHelper->saveConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ID,
                    '',
                    $scope['context_scope'],
                    $scope['context_scope_id']
                );
                $this->apsisCoreHelper->saveConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_SECRET,
                    '',
                    $scope['context_scope'],
                    $scope['context_scope_id']
                );
                $this->apsisCoreHelper->saveConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN,
                    '',
                    $scope['context_scope'],
                    $scope['context_scope_id']
                );
                $this->apsisCoreHelper->saveConfigValue(
                    ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_TOKEN_EXPIRE,
                    '',
                    $scope['context_scope'],
                    $scope['context_scope_id']
                );
                //Clear config cache
                $this->apsisCoreHelper->cleanCache();
            }
        }

        return $this;
    }

    /**
     * @param string $id
     * @param string $secret
     * @param array $scope
     *
     * @return bool
     */
    private function isValid(string $id, string $secret, array $scope)
    {
        $isValid = false;
        $tokenFromApi = $this->apsisCoreHelper->getTokenFromApi(
            $scope['context_scope'],
            $scope['context_scope_id'],
            $id,
            $secret
        );

        if (strlen($tokenFromApi)) {
            $isValid = $this->isMagentoKeySpaceExist($tokenFromApi, $scope);
            ($isValid) ? $this->messageManager->addSuccessMessage(__('API credentials valid.')) :
                $this->messageManager->addWarningMessage(__('API credentials invalid for integration.'));
        } else {
            $this->apsisCoreHelper->log(
                __METHOD__ . ': Authorization has been denied for scope : ' . $scope['context_scope'] .
                ' - id :' . $scope['context_scope_id']
            );
            $this->messageManager->addWarningMessage(__('Authorization has been denied for this request.'));
        }
        return $isValid;
    }

    /**
     * @param string $token
     * @param array $scope
     *
     * @return bool
     */
    private function isMagentoKeySpaceExist(string $token, array $scope)
    {
        $client = $this->apsisCoreHelper->getApiClientFromToken($token);
        $keySpaces = $client->getKeySpaces();

        if (! is_object($keySpaces)) {
            return false;
        }

        if (isset($keySpaces->items)) {
            foreach ($keySpaces->items as $item) {
                if (strpos($item->discriminator, 'magento') !== false) {
                    return true;
                }
            }
        }

        $this->apsisCoreHelper->log(
            __METHOD__ . ': API credentials invalid for integration for scope : ' . $scope['context_scope'] .
            ' - id :' . $scope['context_scope_id']
        );
        return false;
    }
}
