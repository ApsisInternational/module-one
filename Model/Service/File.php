<?php

namespace Apsis\One\Model\Service;

use Throwable;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Magento\Framework\Filesystem\Directory\Write;

class File
{
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
    public function __construct(
        DirectoryList $directoryList,
        WriteFactory $write
    ) {
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
     * @param string $filename
     *
     * @return string
     */
    public function getLogFileContent(string $filename = 'apsis_one')
    {
        switch ($filename) {
            case "apsis_one":
                $filename = 'apsis_one.log';
                break;
            case "system":
                $filename = 'system.log';
                break;
            case "exception":
                $filename = 'exception.log';
                break;
            case "debug":
                $filename = 'debug.log';
                break;
            default:
                return "Log file is not valid. Log file name is " . $filename;
        }

        $contents = '';

        try {
            $pathLogfile = $this->directoryList->getPath('log') . DIRECTORY_SEPARATOR . $filename;
            if (! $this->write->getDriver()->isExists($pathLogfile)) {
                return "Log file does not exist at this moment. File path is " . $pathLogfile;
            }

            $handle = $this->write->getDriver()->fileOpen($pathLogfile, 'r');
            fseek($handle, -500000, SEEK_END);

            if (! $handle) {
                $this->write->getDriver()->fileClose($handle);
                return "Log file is not readable or does not exist at this moment. File path is " . $pathLogfile;
            }

            if ($this->write->getDriver()->stat($pathLogfile)['size'] > 0) {
                $contents = $this->write->getDriver()->fileReadLine(
                    $handle,
                    $this->write->getDriver()->stat($pathLogfile)['size']
                );
                $this->write->getDriver()->fileClose($handle);
            }
        } catch (Throwable $e) {
            $contents = $e->getMessage() . ' : File Name - ' . $filename;
        }

        return $contents;
    }
}
