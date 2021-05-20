<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Logger\Logger;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ResourceInterface;

class Log
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ResourceInterface
     */
    private $moduleResource;

    /**
     * Log constructor.
     *
     * @param Logger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceInterface $moduleResource
     */
    public function __construct(Logger $logger, ScopeConfigInterface $scopeConfig, ResourceInterface $moduleResource)
    {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->moduleResource = $moduleResource;
    }

    /**
     * @param string $classMethodName
     * @param Exception $e
     * @param array $extra
     */
    public function logError(string $classMethodName, Exception $e, array $extra = [])
    {
        $this->error($this->getStringForLog($classMethodName, $e->getMessage(), $e->getTraceAsString()), $extra);
    }

    /**
     * INFO (200): Interesting events.
     *
     * @param string $message
     * @param array $extra
     */
    public function log(string $message, $extra = [])
    {
        $this->logger->info($this->addModuleVersionToMessage($message), $extra);
    }

    /**
     * DEBUG (100): Detailed debug information.
     *
     * @param string $message
     * @param array $response
     * @param array $extra
     */
    public function debug(string $message, array $response = [], array $extra = [])
    {
        $msg = $this->getStringForLog($message, (string) json_encode($response, JSON_PRETTY_PRINT), '');
        $this->logger->debug($this->addModuleVersionToMessage($msg), $extra);
    }

    /**
     * ERROR (400): Runtime errors.
     *
     * @param string $message
     * @param array $extra
     */
    public function error(string $message, $extra = [])
    {
        $this->logger->error($this->addModuleVersionToMessage($message), $extra);
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
            $this->logError(__METHOD__, $e);
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
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * Invalidate cache by type
     * Clean scopeCodeResolver
     *
     * @return void
     */
    public function cleanCache()
    {
        $this->scopeConfig->clean();
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
        $string = ' - Class & Method: ' . $functionName . ' - Message: ' . $text;
        return strlen($trace) ? $string . PHP_EOL . $trace : $string;
    }

    /**
     * @param string $message
     *
     * @return string
     */
    private function addModuleVersionToMessage(string $message)
    {

        return '(v' . $this->moduleResource->getDbVersion('Apsis_One') . ') ' . $message;
    }
}
