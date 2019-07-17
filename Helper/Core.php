<?php

namespace Apsis\One\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;

class Core extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    const APSIS_SUBSCRIBER_TABLE = 'apsis_subscriber';

    /**
     * Core constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Context $context, StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * Get selected scope object in admin
     *
     * @return StoreInterface|WebsiteInterface
     */
    public function getSelectedScopeObjectInAdmin()
    {
        $storeId = $this->_request->getParam('store');
        if ($storeId) {
            return $this->storeManager->getStore($storeId);
        }

        $websiteId = $this->_request->getParam('website', 0);
        return $this->storeManager->getWebsite($websiteId);
    }

    /**
     * Get selected scope in admin
     *
     * @return array
     */
    public function getSelectedScopeInAdmin()
    {
        $scope = [];
        $storeId = $this->_request->getParam('store');
        if ($storeId) {
            $scope['context_scope'] = 'stores';
            $scope['context_scope_id'] = $storeId;
            return $scope;
        }

        $websiteId = $this->_request->getParam('website', 0);
        $contextScope = ($websiteId) ? 'websites' : 'default';

        $scope['context_scope'] = $contextScope;
        $scope['context_scope_id'] = $websiteId;
        return $scope;
    }

    /**
     * Get config scope value.
     *
     * @param string $path
     * @param string $contextScope
     * @param null|int $contextScopeId
     *
     * @return mixed
     */
    public function getConfigValue($path, $contextScope = 'default', $contextScopeId = null)
    {
        return $this->scopeConfig->getValue($path, $contextScope, $contextScopeId);
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getMappedValueFromSelectedScope($path)
    {
        $scope = $this->getSelectedScopeInAdmin();
        return $this->getConfigValue(
            $path,
            $scope['context_scope'],
            $scope['context_scope_id']
        );
    }
}
