<?php

namespace Apsis\One\Block;

use Magento\Framework\View\Element\Template;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Helper\Config as ApsisConfigHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Script block
 *
 * @api
 */
class Script extends Template
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Cart constructor.
     *
     * @param Template\Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        array $data = []
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getScriptText()
    {
        $store = $this->apsisCoreHelper->getStore();
        $isEnabled = $this->apsisCoreHelper->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED,
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );
        $isTrackingEnabled = $this->apsisCoreHelper->getConfigValue(
            ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_TRACKING_ENABLED,
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );

        if ($isEnabled && $isTrackingEnabled) {
            return (string) $this->apsisCoreHelper->getConfigValue(
                ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_TRACKING_SCRIPT,
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );
        }
        return '';
    }
}
