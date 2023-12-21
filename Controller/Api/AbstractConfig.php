<?php

namespace Apsis\One\Controller\Api;

use Apsis\One\Service\ConfigService;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;

abstract class AbstractConfig extends AbstractApi
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ConfigService $service
     * @param CustomerFactory $customerFactory
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        ConfigService $service,
        CustomerFactory $customerFactory,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($request, $response, $service, $customerFactory, $encryptor);
    }
}
