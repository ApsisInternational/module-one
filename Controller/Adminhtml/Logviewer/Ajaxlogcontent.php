<?php

namespace Apsis\One\Controller\Adminhtml\Logviewer;

use Apsis\One\Model\Service\File;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Json\Helper\Data;

class Ajaxlogcontent extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::config';

    /**
     * @var File
     */
    private $file;

    /**
     * @var Data
     */
    private $jsonHelper;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * Ajaxlogcontent constructor.
     *
     * @param Context $context
     * @param File $file
     * @param Data $jsonHelper
     * @param Escaper $escaper
     */
    public function __construct(Context $context, File $file, Data $jsonHelper, Escaper $escaper)
    {
        $this->file = $file;
        $this->jsonHelper = $jsonHelper;
        $this->escaper = $escaper;
        parent::__construct($context);
    }

    /**
     * Ajax get log file content.
     */
    public function execute()
    {
        $logFile = $this->getRequest()->getParam('log');
        switch ($logFile) {
            case "apsis_one":
                $header = 'APSIS Log';
                break;
            case "system":
                $header = 'Magento System Log';
                break;
            case "exception":
                $header = 'Magento Exception Log';
                break;
            case "debug":
                $header = 'Magento Debug Log';
                break;
            default:
                $header = 'APSIS Log';
        }
        $content = nl2br($this->escaper->escapeHtml($this->file->getLogFileContent($logFile)));
        $response = [
            'content' => $content,
            'header' => $header
        ];
        $this->getResponse()->representJson($this->jsonHelper->jsonEncode($response));
    }
}
