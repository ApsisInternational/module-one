<?php

namespace Apsis\One\Controller\Adminhtml\Logviewer;

use Apsis\One\Model\Service\File;
use Apsis\One\Model\Service\Log;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;

class Ajaxlogcontent extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::logviewer';

    /**
     * @var File
     */
    private $file;

    /**
     * @var Json
     */
    private $jsonHelper;

    /**
     * @var Log
     */
    private $logHelper;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * Ajaxlogcontent constructor.
     *
     * @param Context $context
     * @param File $file
     * @param Json $jsonHelper
     * @param Escaper $escaper
     * @param Log $log
     */
    public function __construct(Context $context, File $file, Json $jsonHelper, Escaper $escaper, Log $log)
    {
        $this->file = $file;
        $this->jsonHelper = $jsonHelper;
        $this->escaper = $escaper;
        $this->logHelper = $log;
        parent::__construct($context);
    }

    /**
     * Ajax get log file content.
     */
    public function execute()
    {
        $header = 'APSIS Log';
        try {
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
        } catch (Exception $e) {
            $this->logHelper->logError(__METHOD__, $e);
            $content = $e->getMessage();
        }

        $response = [
            'content' => $content,
            'header' => $header
        ];
        $this->getResponse()->representJson($this->jsonHelper->serialize($response));
    }
}
