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
     * @param string $message
     * @param array $extra
     */
    public function log(string $message, array $extra = [])
    {
        $this->logger->info($this->addModuleVersionToMessage("Message : $message"), $extra);
    }

    /**
     * @param string $message
     * @param array $response
     * @param array $extra
     */
    public function debug(string $message, array $response = [], array $extra = [])
    {
        $this->logger->debug($this->getStringForLog(['Message' => $message, 'Information' => $response]), $extra);
    }

    /**
     * @param string $classMethodName
     * @param Exception $e
     * @param array $extra
     */
    public function logError(string $classMethodName, Exception $e, array $extra = [])
    {
        $info = [
            'Method' => $classMethodName,
            'Exception' => $e->getMessage(),
            'Trace' => str_replace(PHP_EOL, PHP_EOL . "        ", PHP_EOL . $e->getTraceAsString())
        ];
        $this->error($this->getStringForLog($info), $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     */
    public function error(string $message, array $extra = [])
    {
        $this->logger->error($this->addModuleVersionToMessage($message), $extra);
    }


    /**
     * @param array $info
     *
     * @return string
     */
    private function getStringForLog(array $info)
    {
        return stripcslashes($this->addModuleVersionToMessage(json_encode($info, JSON_PRETTY_PRINT)));
    }

    /**
     * @param string $message
     *
     * @return string
     */
    private function addModuleVersionToMessage(string $message)
    {
        $version = $this->moduleResource->getDbVersion('Apsis_One');
        $version = ($version) ?: Config::MODULE_VERSION;
        return '(Module v' . $version . ') ' . $message;
    }

    /**
     * @param mixed $data
     *
     * @return string|bool
     */
    public function serialize($data)
    {
        try {
            return json_encode($data);
        } catch (Exception $e) {
            $this->logError(__METHOD__, $e);
            return '[]';
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
     */
    public function cleanCache()
    {
        $this->scopeConfig->clean();
    }
}
