<?php

namespace Apsis\One\Observer\Adminhtml;

use Apsis\One\ApiClient\Client;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Store\Model\ScopeInterface;
use Exception;

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
        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();

        try {
            $groups = $this->context->getRequest()->getPost('groups');

            if (isset($groups['oauth']['fields']['id']['inherit']) ||
                isset($groups['oauth']['fields']['secret']['inherit'])
            ) {
                if (in_array($scope['context_scope'], [ScopeInterface::SCOPE_STORES, ScopeInterface::SCOPE_WEBSITES])) {
                    $this->apsisCoreHelper->removeTokenConfig($scope['context_scope'], $scope['context_scope_id']);
                }
                return $this;
            }

            $id = $groups['oauth']['fields']['id']['value'] ?? false;
            $secret = $groups['oauth']['fields']['secret']['value'] ?? false;
            $region = $groups['oauth']['fields']['region']['value'] ?? false;

            if ($id && $secret && $region) {
                try {
                    $this->apsisCoreHelper->validateIsUrlReachable($this->apsisCoreHelper->buildHostName($region));
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper
                        ->disableAccountAndRemoveTokenConfig($scope['context_scope'], $scope['context_scope_id']);
                    $msg = '. Cannot enable account. Host for URL must be whitelisted first.';
                    $this->messageManager->addWarningMessage(__($e->getMessage() . $msg));
                    return $this;
                }

                $check = $this->validateApiCredentials($id, $secret, $region, $scope);
                if ($check === true) {
                    $this->messageManager
                        ->addSuccessMessage(__('API credentials are valid and Magento keySpace exist.'));
                } else {
                    $this->apsisCoreHelper
                        ->disableAccountAndRemoveTokenConfig($scope['context_scope'], $scope['context_scope_id']);
                    $this->apsisCoreHelper->log($check);
                    $this->messageManager->addWarningMessage(__($check));
                }

                try {
                    $this->apsisCoreHelper
                        ->validateIsUrlReachable($this->apsisCoreHelper->buildFileUploadHostName($region));
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logError(__METHOD__, $e);
                    $this->apsisCoreHelper->disableProfileSync($scope['context_scope'], $scope['context_scope_id']);
                    $msg = '. Profile sync is disabled and will not work until host for URL is whitelisted.';
                    $this->messageManager->addWarningMessage(__($e->getMessage() . $msg));
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper
                ->disableAccountAndRemoveTokenConfig($scope['context_scope'], $scope['context_scope_id']);
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addWarningMessage(__('Something went wrong, please check exception logs.'));
        }

        return $this;
    }

    /**
     * @param string $id
     * @param string $secret
     * @param string $region
     * @param array $scope
     *
     * @return bool|string
     */
    private function validateApiCredentials(string $id, string $secret, string $region, array $scope)
    {
        try {
            $client = $this->apsisCoreHelper->getApiClient(
                $scope['context_scope'],
                $scope['context_scope_id'],
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
