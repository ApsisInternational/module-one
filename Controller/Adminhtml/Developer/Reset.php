<?php

namespace Apsis\One\Controller\Adminhtml\Developer;

use Magento\Backend\App\AbstractAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Apsis\One\Model\Developer;

class Reset extends AbstractAction
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::config';

    /**
     * @var Developer
     */
    private $developer;

    /**
     * Reset constructor.
     *
     * @param Context $context
     * @param Developer $developer
     */
    public function __construct(
        Context $context,
        Developer $developer
    ) {
        $this->developer = $developer;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        ($this->developer->resetModule()) ? $this->messageManager->addSuccessMessage('Module successfully reset') :
            $this->messageManager->addWarningMessage('Unable to reset module, please check log file.');
        $this->_redirect($this->_redirect->getRefererUrl());
    }
}
