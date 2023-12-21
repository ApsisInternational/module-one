<?php

namespace Apsis\One\Controller\Api\IntegrationConfig;

use Apsis\One\Controller\Api\AbstractConfig;
use Magento\Framework\App\ResponseInterface;

class Index extends AbstractConfig
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = ['ConfigsList' => ['GET', 'HEAD']];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = ['getConfigsList' => ['query' => ['sections' => 'string']]];

    /**
     * @return ResponseInterface
     */
    protected function getConfigsList(): ResponseInterface
    {
        try {
            $record = $this->service->getConfig($this->store->getId(), $this->queryParams['sections'], false);
            if (is_int($record)) {
                return $this->sendErrorInResponse($record);
            }
            return $this->sendResponse(200, null, json_encode($record));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
