<?php

namespace Apsis\One\Controller\Api\SystemInformation;

use Apsis\One\Controller\Api\AbstractApi;
use Apsis\One\Service\BaseService;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\ProductMetadataInterface;

class Index extends AbstractApi
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = [
        'SystemInformation' => ['GET', 'HEAD']
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getSystemInformation' => ['query' => []]
    ];

    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param BaseService $service
     * @param CustomerFactory $customerFactory
     * @param EncryptorInterface $encryptor
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        BaseService $service,
        CustomerFactory $customerFactory,
        EncryptorInterface $encryptor,
        ProductMetadataInterface $productMetadata
    ) {
        $this->productMetadata = $productMetadata;
        parent::__construct($request, $response, $service, $customerFactory, $encryptor);
    }

    /**
     * @return ResponseInterface
     */
    protected function getSystemInformation(): ResponseInterface
    {
        $info = [
            'system_id' => 'adobe-commerce',
            'system_access_url' => $this->service->generateSystemAccessUrl($this->getRequest()),
            'system_version' => $this->productMetadata->getVersion(),
            'supported_integration_versions' => ['v3.0'],
            'supported_features' => ['PROFILE_SYNC', 'PROFILE_LIST_SYNC'],
            'supported_marketing_automation_nodes' => []
        ];
        return $this->sendResponse(200, null, json_encode($info));
    }
}
