<?php

namespace Apsis\One\Block;

use Exception;
use Magento\Framework\View\Element\Template;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
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
     * Cart constructor.
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
     * @return string
     */
    public function getScriptText()
    {
        $text = '';

        try {
            $isTrackingEnabled = (boolean) $this->_storeManager
                ->getStore()
                ->getConfig(ApsisConfigHelper::TRACKING_ENABLED);
            $trackingTextConfig = (string) $this->_storeManager
                ->getStore()
                ->getConfig(ApsisConfigHelper::TRACKING_SCRIPT);

            if ($isTrackingEnabled && strlen($trackingTextConfig)) {
                return $trackingTextConfig;
            }

        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }

        return $text;
    }
}
