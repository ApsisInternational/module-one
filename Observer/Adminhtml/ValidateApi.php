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
        try {
            $groups = $this->context->getRequest()->getPost('groups');

            if (isset($groups['oauth']['fields']['id']['inherit']) ||
                isset($groups['oauth']['fields']['secret']['inherit'])
            ) {
                $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
                if (in_array($scope['context_scope'], [ScopeInterface::SCOPE_STORES, ScopeInterface::SCOPE_WEBSITES])) {
                    $this->apsisCoreHelper->removeTokenConfig($scope['context_scope'], $scope['context_scope_id']);
                }
                return $this;
            }

            $id = $groups['oauth']['fields']['id']['value'] ?? false;
            $secret = $groups['oauth']['fields']['secret']['value'] ?? false;
            $region = $groups['oauth']['fields']['region']['value'] ?? false;

            if ($id && $secret && $region) {
                $this->validateApiCredentials($id, $secret, $region);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }

        return $this;
    }

    /**
     * @param string $id
     * @param string $secret
     * @param string $region
     */
    private function validateApiCredentials(string $id, string $secret, string $region)
    {
        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();

        try {
            $host = $this->apsisCoreHelper->buildHostName($region);
            $this->apsisCoreHelper->validateIsUrlReachable($host);

            $client = $this->apsisCoreHelper->getApiClient(
                $scope['context_scope'],
                $scope['context_scope_id'],
                true,
                $region,
                $id,
                $secret
            );

            if ($client) {
                $isValid = false;
                $keySpaces = $client->getKeySpaces();

                if (is_object($keySpaces) && isset($keySpaces->items)) {
                    foreach ($keySpaces->items as $item) {
                        if (strpos($item->discriminator, 'magento') !== false) {
                            $isValid = true;
                        }
                    }
                }

                if ($isValid) {
                    $this->messageManager->addSuccessMessage(
                        __('API credentials are valid and Magento keySpace exist.')
                    );
                } else {
                    $msg = 'API credentials are invalid for this integration. Magento keySpace does not exist.';
                    $this->apsisCoreHelper->log($msg);
                    $this->messageManager->addWarningMessage(__($msg));
                }
            } else {
                $msg = 'Unable to generate access token. Authorization has been denied for this request.';
                $this->apsisCoreHelper->log(__METHOD__ . ": " . $msg, $scope);
                $this->messageManager->addWarningMessage(__($msg));
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->disableAccountAndRemoveTokenConfig(
                $scope['context_scope'],
                $scope['context_scope_id']
            );
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            $this->messageManager->addWarningMessage(__($e->getMessage()));
        }
    }
}
