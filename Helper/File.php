<?php

namespace Apsis\One\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Magento\Framework\Filesystem\Directory\Write;

class File extends AbstractHelper
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
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param WriteFactory $write
     *
     * @throws FileSystemException
     */
    public function __construct(
        Context $context,
        DirectoryList $directoryList,
        WriteFactory $write
    ) {
        $this->directoryList = $directoryList;
        $this->write = $write->create($this->getOutputFolder());
        parent::__construct($context);
    }

    /**
     * @return string
     *
     * @throws FileSystemException
     */
    private function getOutputFolder()
    {
        return $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . 'apsis';
    }

    /**
     * @param $filename
     *
     * @return string
     *
     * @throws FileSystemException
     */
    public function getFilePath($filename)
    {
        return $this->getOutputFolder() . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * @param string $file
     * @param array $data
     *
     * @throws FileSystemException
     * @throws ValidatorException
     */
    public function outputCSV($file, $data)
    {
        $resource = $this->write->openFile($file, 'a');
        $resource->lock();
        $resource->writeCsv($data);
        $resource->unlock();
        $resource->close();
    }
}
