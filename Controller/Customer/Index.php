<?php

namespace Apsis\One\Controller\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Newsletter\Controller\Manage;
use Magento\Newsletter\Model\SubscriberFactory;

class Index extends Manage
{
    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Session $customerSession
     * @param SubscriberFactory $subscriberFactory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        SubscriberFactory $subscriberFactory
    ) {
        $this->subscriberFactory = $subscriberFactory;
        parent::__construct($context, $customerSession);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        if (! $this->_customerSession->authenticate()) {
            $this->_response->setRedirect($this->_url->getUrl('newsletter/manage/index'));
        }

        $subscriber = $this->subscriberFactory->create()
            ->loadByCustomerId($this->_customerSession->getCustomer()->getId());
        if (empty($subscriber->getId()) || ! $subscriber->isSubscribed()) {
            $this->_response->setRedirect($this->_url->getUrl('newsletter/manage/index'));
        } else {
            $this->_view->loadLayout();
            $this->_view->getPage()->getConfig()->getTitle()->set(__('Newsletter Subscription'));
            $this->_view->getPage()->setHeader(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0',
                true
            );
            $this->_view->renderLayout();
        }
    }
}
