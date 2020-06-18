<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Logger\Logger;
use Exception;

class Log
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * Log constructor.
     *
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $classMethodName
     * @param string $text
     * @param string $trace
     * @param array $extra
     */
    public function logMessage(string $classMethodName, string $text, string $trace = '', array $extra = [])
    {
        $this->log($this->getStringForLog($classMethodName, $text, $trace), $extra);
    }

    /**
     * @param string $functionName
     * @param string $text
     * @param string $trace
     *
     * @return string
     */
    private function getStringForLog(string $functionName, string $text, string $trace)
    {
        $string = ' - Class & Method: ' . $functionName . ' - Text: ' . $text;
        return strlen($trace) ? $string . PHP_EOL . $trace : $string;
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
            $this->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            $this->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return [];
        }
    }
}
