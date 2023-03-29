<?php

namespace Apsis\One\Controller\Api;

class Error extends AbstractApi
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        return $this->sendErrorInResponse();
    }
}
