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
use Throwable;

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
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();

        try {
            $groups = $this->request->getPost('groups');

            //Need to remove toke configs for current context if set to inherit parent's context.
            if ($this->apsisCoreHelper->isInheritConfig($groups)) {

                if (in_array($scope['context_scope'], [ScopeInterface::SCOPE_STORES, ScopeInterface::SCOPE_WEBSITES])) {
                    $this->disableAccountAndRemoveConfig($scope, true);
                }

                return $this;
            }

            if ($this->registry->registry(Value::REGISTRY_NAME_FOR_STATUS_CHECK) === Value::FAIL) {
                $this->disableAccountAndRemoveConfig($scope);
                return $this;
            }

        } catch (Throwable $e) {
            $this->disableAccountAndRemoveConfig($scope);
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addWarningMessage(__('Something went wrong, please check logs.'));
        }

        return $this;
    }

    /**
     * @param array $scope
     * @param bool $isRemoveOnlyTokenConfig
     */
    private function disableAccountAndRemoveConfig(array $scope, bool $isRemoveOnlyTokenConfig = false)
    {
        if ($isRemoveOnlyTokenConfig) {
            $this->apsisCoreHelper->removeTokenConfig(
                __METHOD__,
                $scope['context_scope'],
                $scope['context_scope_id'],
                false
            );
        } else {
            $this->apsisCoreHelper->disableAccountAndRemoveTokenConfig(
                __METHOD__,
                $scope['context_scope'],
                $scope['context_scope_id']
            );
        }
    }
}
