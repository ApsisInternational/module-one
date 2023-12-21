<?php

namespace Apsis\One\Model\Adminhtml\Config\Backend;

use Apsis\One\Service\BaseService;
use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

class HistoricalEventComment implements CommentInterface
{
    /**
     * @var BaseService
     */
    private BaseService $service;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param BaseService $service
     * @param RequestInterface $request
     */
    public function __construct(BaseService $service, RequestInterface $request)
    {
        $this->service = $service;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function getCommentText($elementValue): string
    {
        if ($storeId = $this->request->getParam(StoreManagerInterface::CONTEXT_STORE)) {
            $config = $this->service->getStoreConfig(
                $this->service->getStore($storeId),
                BaseService::PATH_CONFIG_EVENT_PREVIOUS_HISTORICAL
            );
        }
        $noRecord = __('NO RECORD OF PREVIOUS HISTORICAL EVENT SYNC EXIST FOR CURRENT INSTALLATION');
        $yesRecord = __('PREVIOUSLY CHOSEN DURATION FOR HISTORICAL EVENT WAS %s');
        return sprintf(
            "<p class='note'><span>%s</span></p>",
            empty($config)? $noRecord : sprintf($yesRecord, $config)
        );
    }
}
