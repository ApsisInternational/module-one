<?php

namespace Apsis\One\Block\Adminhtml;

use Apsis\One\Model\Service\Log;
use Magento\Backend\Block\Widget\Container;
use Apsis\One\Model\Service\File;
use Magento\Backend\Block\Widget\Context;
use Throwable;

class Logviewer extends Container
{
    /**
     * @var string
     */
    public $_template = 'log.phtml';

    /**
     * @var Log
     */
    private Log $logHelper;

    /**
     * @var File
     */
    public File $file;

    /**
     * @param Context $context
     * @param File $file
     * @param Log $log
     *
     * @param array $data
     */
    public function __construct(Context $context, File $file, Log $log, array $data = [])
    {
        $this->file = $file;
        $this->logHelper = $log;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getFileContent(): string
    {
        try {
            return $this->file->getLogFileContent();
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return $e->getMessage();
        }
    }
}
