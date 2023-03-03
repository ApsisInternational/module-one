<?php

namespace Apsis\One\Block\Adminhtml;

use Apsis\One\Model\Service\Log;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
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
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param Log $log
     *
     * @param array $data
     */
    public function __construct(Context $context, DirectoryList $directoryList, Log $log, array $data = [])
    {
        $this->logHelper = $log;
        $this->directoryList = $directoryList;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getFileContent(): string
    {
        try {
            $logFile = $this->directoryList->getPath('log') . DIRECTORY_SEPARATOR . 'apsis_one.log';
            if (! file_exists($logFile)) {
                $contents = sprintf('Log file does not exist. File path is %s', $logFile);
            } elseif (! is_readable($logFile)) {
                $contents = sprintf('Log file is not readable. File path is %s', $logFile);
            } else {
                $size = filesize($logFile);
                $contents = file_get_contents($logFile, false, null, '-' . $size, 2000000);
            }
            return $contents;
        } catch (Throwable $e) {
            $this->logHelper->logError(__METHOD__, $e);
            return $e->getMessage();
        }
    }
}
