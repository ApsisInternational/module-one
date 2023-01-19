<?php

namespace Apsis\One\Controller\Customer;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Newsletter\Controller\Manage;
use Magento\Newsletter\Model\SubscriberFactory;

class Index extends Manage
{
    /**
     * @var SubscriberFactory
     */
    private SubscriberFactory $subscriberFactory;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Session $customerSession
     * @param SubscriberFactory $subscriberFactory
     */
    public function __construct(Context $context, Session $customerSession, SubscriberFactory $subscriberFactory)
    {
        $this->subscriberFactory = $subscriberFactory;
        parent::__construct($context, $customerSession);
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        try {
            if (! $this->_customerSession->authenticate()) {
                $this->_response->setRedirect($this->_url->getUrl('newsletter/manage/index'));
            }
        } catch (Exception $e) {
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
