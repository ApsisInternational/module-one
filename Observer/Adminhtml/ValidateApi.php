<?php

namespace Apsis\One\Observer\Adminhtml;

use Apsis\One\Model\Config\Backend\Value;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Exception;

class ValidateApi implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * ValidateApi constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param Registry $registry
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        RequestInterface $request,
        ManagerInterface $messageManager,
        Registry $registry
    ) {
        $this->registry = $registry;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->request = $request;
        $this->messageManager = $messageManager;
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
            $groups = $this->request->getPost('groups');

            //Need to remove toke configs for current context if set to inherit parent's context.
            if (isset($groups['oauth']['fields']['id']['inherit']) ||
                isset($groups['oauth']['fields']['secret']['inherit'])
            ) {
                if (in_array($scope['context_scope'], [ScopeInterface::SCOPE_STORES, ScopeInterface::SCOPE_WEBSITES])) {
                    $this->apsisCoreHelper->removeTokenConfig($scope['context_scope'], $scope['context_scope_id']);
                }

                return $this;
            }

            //Additional measure in case registry exist, means should disable account
            if ($this->registry->registry(Value::REGISTRY_NAME_FOR_ERROR)) {
                $this->apsisCoreHelper->disableAccountAndRemoveTokenConfig(
                    $scope['context_scope'],
                    $scope['context_scope_id']
                );

                return $this;
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->disableAccountAndRemoveTokenConfig(
                $scope['context_scope'],
                $scope['context_scope_id']
            );
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addWarningMessage(__('Unable Something went wrong, please check exception logs.'));
        }

        return $this;
    }
}
