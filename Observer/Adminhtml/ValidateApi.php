<?php

namespace Apsis\One\Observer\Adminhtml;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Apsis\One\Helper\Config as ApsisConfigHelper;

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
            /** ToDo remove token and token expiry */
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
        $this->apsisCoreHelper->log('----VALIDATING ACCOUNT---');

        $tokenFromApi = $this->apsisCoreHelper->getTokenFromApi(
            $scope['context_scope'],
            $scope['context_scope_id'],
            $id,
            $secret
        );
        if (strlen($tokenFromApi)) {
            $this->apsisCoreHelper->log(
                'API Credentials Valid for scope : ' . $scope['context_scope'] . ' - id : ' . $scope['context_scope_id']
            );
            $this->messageManager->addSuccessMessage(__('API Credentials Valid.'));
            return true;
        }

        $this->apsisCoreHelper->log(
            'Authorization has been denied for scope : ' . $scope['context_scope'] .
            ' - id :' . $scope['context_scope_id']
        );
        $this->messageManager->addWarningMessage(__('Authorization has been denied for this request.'));
        return false;
    }
}
