<?php

namespace Apsis\One\Controller\Api\IntegrationConfig;

use Apsis\One\Controller\Api\AbstractConfig;
use Magento\Framework\App\ResponseInterface;

class Configs extends AbstractConfig
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = true;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = ['Config' => ['GET', 'HEAD', 'POST', 'DELETE']];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getConfig' => ['query' => []],
        'deleteConfig' => ['query' => []],
        'postConfig' => [
            'query' => [],
            'post' => [
                'account_id' => 'string',
                'section_id' => 'int',
                'one_api_key' => 'array',
                'api_base_url' => 'string',
                'section_discriminator' => 'string',
                'keyspace_discriminator' => 'string'
            ]
        ],
    ];

    /**
     * @return ResponseInterface
     */
    protected function getConfig(): ResponseInterface
    {
        try {
            $record = $this->service->getConfig($this->store->getId(), $this->taskId);
            if (is_int($record)) {
                return $this->sendErrorInResponse($record);
            }
            return $this->sendResponse(200, null, json_encode($record));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function postConfig(): ResponseInterface
    {
        try {
            $status = $this->service->createConfig($this->store->getId(), $this->taskId, $this->requestBody);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }
            return $this->sendResponse(204);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function deleteConfig(): ResponseInterface
    {
        try {
            $status = $this->service->deleteConfig($this->store->getId(), $this->taskId);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }
            return $this->sendResponse(200);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
