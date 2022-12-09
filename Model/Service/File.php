<?php

namespace Apsis\One\Model\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\Write;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Throwable;

class File
{
    const LOG_FILE_NAME = 'apsis_one.log';
    const APSIS_FOLDER = 'apsis';
    const FOLDER_ROOT = 'log';
    const MSG_NOT_EXIST = 'Log file does not exist. File path is %s';
    const MSG_NOT_READABLE = 'Log file is not readable. File path is %s';

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var Write
     */
    private $write;

    /**
     * File constructor.
     *
     * @param DirectoryList $directoryList
     * @param WriteFactory $write
     *
     * @throws Throwable
     */
    public function __construct(DirectoryList $directoryList, WriteFactory $write)
    {
        $this->directoryList = $directoryList;
        $this->write = $write->create($this->getOutputFolder());
    }

    /**
     * @return string
     *
     * @throws Throwable
     */
    private function getOutputFolder()
    {
        return $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . 'apsis';
    }

    /**
     * @param string $filename
     *
     * @return string
     *
     * @throws Throwable
     */
    public function getFilePath(string $filename)
    {
        return $this->getOutputFolder() . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * @param string $filePath
     *
     * @return bool
     *
     * @throws Throwable
     */
    public function deleteFile(string $filePath)
    {
        if (empty($filePath)) {
            return true;
        }

        return $this->write->delete($filePath);
    }

    /**
     * @param string $file
     * @param array $data
     *
     * @throws Throwable
     */
    public function outputCSV($file, $data)
    {
        $resource = $this->write->openFile($file, 'a');
        $resource->lock();
        $resource->writeCsv($data);
        $resource->unlock();
        $resource->close();
    }

    /**
     * @return string
     */
    public function getLogFileContent()
    {
        try {
            $logFile = $this->directoryList->getPath(self::FOLDER_ROOT) . DIRECTORY_SEPARATOR . self::LOG_FILE_NAME;
            if (! file_exists($logFile)) {
                $contents = sprintf(self::MSG_NOT_EXIST, $logFile);
            } elseif (! is_readable($logFile)) {
                $contents = sprintf(self::MSG_NOT_READABLE, $logFile);
            } else {
                $size = filesize($logFile);
                $contents = file_get_contents($logFile, false, null, '-' . $size, 2000000);
            }
        } catch (Throwable $e) {
            $contents = $e->getMessage();
        }

        return $contents;
    }
}
