<?php

namespace Apsis\One\Controller\Api\SystemInformation;

use Apsis\One\Controller\Api\AbstractApi;
use Apsis\One\Service\BaseService;
use Magento\Framework\App\ResponseInterface;

class Index extends AbstractApi
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = ['SystemInformation' => ['GET', 'HEAD']];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = ['getSystemInformation' => ['query' => []]];

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
            'supported_features' => ['PROFILE_SYNC', 'PROFILE_LIST_SYNC'],
            'supported_marketing_automation_nodes' => []
        ];
        return $this->sendResponse(200, null, json_encode($info));
    }
}
