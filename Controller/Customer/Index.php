<?php

namespace Apsis\One\Controller\Customer;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Newsletter\Controller\Manage;

class Index extends Manage
{
    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $this->_view->loadLayout();
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Newsletter Subscription'));
        $this->_view->renderLayout();
    }
}
