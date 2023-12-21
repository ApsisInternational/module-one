<?php

namespace Apsis\One\Controller;

use Apsis\One\Service\BaseService;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;

abstract class AbstractAction implements ActionInterface
{
    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var ResponseInterface
     */
    protected ResponseInterface $response;

    /**
     * @var BaseService
     */
    protected BaseService $service;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param BaseService $service
     */
    public function __construct(RequestInterface $request, ResponseInterface $response, BaseService $service)
    {
        $this->request = $request;
        $this->response = $response;
        $this->service = $service;
    }

    /**
     * @return RequestInterface
     */
    protected function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * @return ResponseInterface
     */
    protected function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
