<?php

namespace Apsis\One\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Apsis\One\Logger\Logger;
use Exception;

class Log extends AbstractHelper
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * Log constructor.
     *
     * @param Context $context
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        Logger $logger
    ) {
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * @param string $classMethodName
     * @param string $text
     */
    public function logMessage(string $classMethodName, string $text)
    {
        $this->log($this->getStringForLog($classMethodName, $text));
    }

    /**
     * @param string $functionName
     * @param string $text
     *
     * @return string
     */
    private function getStringForLog(string $functionName, string $text)
    {
        return ' - Class & Method: ' . $functionName . ' - Text: ' . $text;
    }

    /**
     * INFO (200): Interesting events.
     *
     * @param string $message
     * @param array $extra
     */
    public function log(string $message, $extra = [])
    {
        $this->logger->info($message, $extra);
    }

    /**
     * DEBUG (100): Detailed debug information.
     *
     * @param string $message
     * @param array $extra
     */
    public function debug(string $message, $extra = [])
    {
        $this->logger->debug($message, $extra);
    }

    /**
     * ERROR (400): Runtime errors.
     *
     * @param string $message
     * @param array $extra
     */
    public function error(string $message, $extra = [])
    {
        $this->logger->error($message, $extra);
    }

    /**
     * @param string|int|float|bool|array|null $data
     * @return string|bool
     */
    public function serialize($data)
    {
        try {
            return json_encode($data);
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage());
            return '{}';
        }
    }

    /**
     * @param string $string
     *
     * @return array|bool|float|int|mixed|string|null|object
     */
    public function unserialize(string $string)
    {
        try {
            return json_decode($string);
        } catch (Exception $e) {
            $this->logMessage(__METHOD__, $e->getMessage());
            return [];
        }
    }
}
