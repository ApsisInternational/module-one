<?php

namespace Apsis\One\Controller\Adminhtml\ProfileBatch;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\AbstractAction;
use Magento\Backend\Model\View\Result\Page;

class Index extends AbstractAction
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::batched';

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
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Apsis_One::batch');
        $resultPage->addBreadcrumb(__('Batched Profile'), __('Batched Profiles'));
        $resultPage->addBreadcrumb(__('Reports'), __('Reports'));
        $resultPage->getConfig()->getTitle()->prepend(__('Batched Profiles'));
        return $resultPage;
    }
}
