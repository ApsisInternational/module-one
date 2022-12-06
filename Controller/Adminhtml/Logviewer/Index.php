<?php

namespace Apsis\One\Controller\Adminhtml\Logviewer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::logviewer';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Apsis_One::logviewer');
        $resultPage->addBreadcrumb(__('APSIS Logs'), __('APSIS Logs'));
        $resultPage->addBreadcrumb(__('Reports'), __('Reports'));
        $resultPage->getConfig()->getTitle()->prepend(__('APSIS Logs'));
        return $resultPage;
    }
}
