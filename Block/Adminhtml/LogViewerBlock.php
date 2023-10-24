<?php

namespace Apsis\One\Block\Adminhtml;

use Apsis\One\Service\BaseService;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Throwable;

class LogViewerBlock extends Container
{
    /**
     * @var string
     */
    public $_template = 'log.phtml';

    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param BaseService $baseService
     * @param array $data
     */
    public function __construct(
        Context $context,
        DirectoryList $directoryList,
        BaseService $baseService,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->baseService = $baseService;
        $this->directoryList = $directoryList;
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
                $contents = file_get_contents($logFile, false, null, '-' . $size);
            }
            return $contents;
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
            return $e->getMessage();
        }
    }
}
