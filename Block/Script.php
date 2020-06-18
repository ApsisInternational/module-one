<?php

namespace Apsis\One\Block;

use Exception;
use Magento\Framework\View\Element\Template;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Store\Model\StoreManagerInterface;
use Apsis\One\Model\Service\Log as ApsisLogHelper;

/**
 * Script block
 *
 * @api
 */
class Script extends Template
{
    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Cart constructor.
     *
     * @param Template\Context $context
     * @param ApsisLogHelper $apsisLogHelper
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ApsisLogHelper $apsisLogHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->apsisLogHelper = $apsisLogHelper;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getScriptText()
    {
        try {
            $store = $this->storeManager->getStore();
            $isAccountEnabled = (boolean) $store->getConfig(ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED);
            $isTrackingEnabled = (boolean) $store->getConfig(
                ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_TRACKING_ENABLED
            );

            if ($isAccountEnabled && $isTrackingEnabled) {
                return (string) $store->getConfig(ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_TRACKING_SCRIPT);
            }
            return '';
        } catch (Exception $e) {
            $this->apsisLogHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return '';
        }
    }
}
