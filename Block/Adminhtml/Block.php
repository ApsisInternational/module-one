<?php

namespace Apsis\One\Block\Adminhtml;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Apsis\One\Model\Service\Config;
use Exception;
use Magento\Store\Model\ScopeInterface;

class Block extends Template
{
    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * Block constructor.
     *
     * @param Template\Context $context
     * @param ApsisLogHelper $apsisLogHelper
     * @param array $data
     */
    public function __construct(Template\Context $context, ApsisLogHelper $apsisLogHelper, array $data = [])
    {
        $this->apsisLogHelper = $apsisLogHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return bool
     */
    public function isProfileDeleteEnabled()
    {
        try {
            $scope = $this->getSelectedScopeInAdmin();
            return (bool) $this->_scopeConfig->getValue(
                Config::PROFILE_SYNC_DELETE_ENABLED,
                $scope['context_scope'],
                $scope['context_scope_id']
            );
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            //Default value
            return true;
        }
    }

    /**
     * @return bool
     */
    public function isSectionAlreadyMapped(){
        try {
            $scope = $this->getSelectedScopeInAdmin();
            return (bool) $this->_scopeConfig->getValue(
                Config::MAPPINGS_SECTION_SECTION,
                $scope['context_scope'],
                $scope['context_scope_id']
            );
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isAccountAlreadyConfigured(){
        try {
            $scope = $this->getSelectedScopeInAdmin();
            $idMapped = (bool) $this->_scopeConfig->getValue(
                Config::ACCOUNTS_OAUTH_ID,
                $scope['context_scope'],
                $scope['context_scope_id']
            );
            $secretMapped = (bool) $this->_scopeConfig->getValue(
                Config::ACCOUNTS_OAUTH_SECRET,
                $scope['context_scope'],
                $scope['context_scope_id']
            );
            $regionMapped = (bool) $this->_scopeConfig->getValue(
                Config::ACCOUNTS_OAUTH_REGION,
                $scope['context_scope'],
                $scope['context_scope_id']
            );

            return $idMapped && $secretMapped && $regionMapped;
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @return string
     */
    public function getResetUrl()
    {
        return $this->escapeUrl($this->_urlBuilder->getUrl('apsis_one/developer/reset'));
    }

    /**
     * Get selected scope in admin
     *
     * @return array
     */
    private function getSelectedScopeInAdmin()
    {
        $scope = [];
        $storeId = $this->_request->getParam('store');
        if ($storeId) {
            $scope['context_scope'] = ScopeInterface::SCOPE_STORES;
            $scope['context_scope_id'] = (int) $storeId;
            return $scope;
        }

        $websiteId = $this->_request->getParam('website', 0);
        $contextScope = ($websiteId) ? ScopeInterface::SCOPE_WEBSITES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

        $scope['context_scope'] = $contextScope;
        $scope['context_scope_id'] = (int) $websiteId;
        return $scope;
    }
}
