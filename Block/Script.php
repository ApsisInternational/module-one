<?php

namespace Apsis\One\Block;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\View\Element\Template;
use Throwable;

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
    private ApsisLogHelper $apsisLogHelper;

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
    public function getTrackingUrl(): string
    {
        $url = '';

        try {
            $isTrackingEnabled = (boolean) $this->_storeManager
                ->getStore()
                ->getConfig(ApsisConfigHelper::TRACKING_ENABLED);
            $trackingTextConfig = (string) $this->_storeManager
                ->getStore()
                ->getConfig(ApsisConfigHelper::TRACKING_SCRIPT);

            preg_match('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $trackingTextConfig, $match);
            if ($isTrackingEnabled && ! empty($match)) {
                $url = str_replace('.js', '', $match[0]);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }

        return $url;
    }
}
