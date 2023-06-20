<?php

namespace Apsis\One\Controller\Api;

use Magento\Framework\App\ResponseInterface;

class Error extends AbstractApi
{
    /**
     * @inheritDoc
     */
    public function execute(): ResponseInterface
    {
        return $this->sendErrorInResponse();
    }
}
