<?php

namespace Apsis\One\Controller\Adminhtml\Developer;

use Apsis\One\Service\DeveloperService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;

class Reset extends Action
{
    /**
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::config';

    /**
     * @var DeveloperService
     */
    private DeveloperService $developerService;

    /**
     * @param Context $context
     * @param DeveloperService $developerService
     */
    public function __construct(Context $context, DeveloperService $developerService)
    {
        $this->developerService = $developerService;
        parent::__construct($context);
    }

    /**
     * @inheirtDoc
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $this->developerService->resetModule() ?
            $this->messageManager->addSuccessMessage('Module is successfully reset.') :
            $this->messageManager->addWarningMessage('Unable to reset module, please check integration log file.');
        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
