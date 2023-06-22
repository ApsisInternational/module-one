<?php

namespace Apsis\One\Controller\Api\SystemInformation;

use Apsis\One\Controller\Api\AbstractApi;
use Apsis\One\Service\BaseService;
use Magento\Framework\App\ResponseInterface;
use Throwable;

class Index extends AbstractApi
{
    const CONFIG_PARAMS = [
        'client_id' => 'string',
        'client_secret' => 'string',
        'api_base_url' => 'string',
        'section_discriminator' => 'string',
        'keyspace_discriminator' => 'string'
    ];

    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = [
        'SystemInformation' => ['GET', 'HEAD'],
        'Config' => ['POST', 'PATCH']
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getSystemInformation' => ['query' => []],
        'postConfig' => ['post' => self::CONFIG_PARAMS],
        'patchConfig' => ['post' => self::CONFIG_PARAMS]
    ];

    /**
     * @return ResponseInterface
     */
    protected function getSystemInformation(): ResponseInterface
    {
        $info = [
            'system_id' => 'magento',
            'system_access_url' => $this->service->generateSystemAccessUrl($this->getRequest()),
            'system_version' => BaseService::MODULE_VERSION,
            'supported_integration_versions' => ['v2.0'],
            'supported_features' => ['PROFILE_SYNC', 'PROFILE_LIST_SYNC', 'UTILIZES_ONE_API'],
            'supported_marketing_automation_nodes' => []
        ];
        return $this->sendResponse(200, null, json_encode($info));
    }

    /**
     * @return ResponseInterface
     */
    protected function postConfig(): ResponseInterface
    {
        return $this->postPatchConfig();
    }

    /**
     * @return ResponseInterface
     */
    protected function patchConfig(): ResponseInterface
    {
        return $this->postPatchConfig();
    }

    /**
     * @return ResponseInterface
     */
    private function postPatchConfig(): ResponseInterface
    {
        try {
            $configs = [
                BaseService::PATH_APSIS_CLIENT_ID => $this->requestBody['client_id'],
                BaseService::PATH_APSIS_CLIENT_SECRET => $this->encryptor->encrypt($this->requestBody['client_secret']),
                BaseService::PATH_APSIS_API_URL => $this->requestBody['api_base_url'],
                BaseService::PATH_APSIS_CONFIG_SECTION => $this->requestBody['section_discriminator'],
                BaseService::PATH_APSIS_CONFIG_KEYSPACE => $this->requestBody['keyspace_discriminator'],
                BaseService::PATH_APSIS_API_TOKEN_EXPIRY => '', // Need to clear it since api credentials has changed
                BaseService::PATH_APSIS_API_TOKEN => '', // Need to clear it since api credentials has changed
            ];
            $check = $this->service->saveStoreConfig($this->store, $configs);
            if (is_string($check)) {
                return $this->sendErrorInResponse(500);
            }

            return $this->sendResponse(204);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
