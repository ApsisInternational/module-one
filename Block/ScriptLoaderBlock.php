<?php

namespace Apsis\One\Block;

use Apsis\One\Service\BaseService;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;
use Throwable;

class ScriptLoaderBlock extends Template
{
    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var Escaper
     */
    public Escaper $escaper;

    /**
     * @param Template\Context $context
     * @param BaseService $baseService
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(Template\Context $context, BaseService $baseService, Escaper $escaper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->baseService = $baseService;
        $this->escaper = $escaper;
    }

    /**
     * @return string
     */
    public function getTrackingUrl(): string
    {
        $url = '';
        try {
            $trackingTextConfig = (string) $this->_storeManager
                ->getStore()
                ->getConfig(BaseService::PATH_CONFIG_TRACKING_SCRIPT);
            if (empty($trackingTextConfig)) {
                return $url;
            }

            preg_match('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $trackingTextConfig, $match);
            if (! empty($match)) {
                $url = str_replace('.js', '', $match[0]);
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
        return $url;
    }
}
