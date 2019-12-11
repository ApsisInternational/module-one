<?php

namespace Apsis\One\Controller\Abandoned;

use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;

class Checkout extends Action
{
    /**
     * @var QuoteResource
     */
    private $quoteResource;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var CustomerSessionFactory
     */
    private $customerSessionFactory;

    /**
     * Checkout constructor.
     *
     * @param Context $context
     * @param QuoteResource $quoteResource
     * @param QuoteFactory $quoteFactory
     * @param CustomerSessionFactory $customerSessionFactory
     */
    public function __construct(
        Context $context,
        QuoteResource $quoteResource,
        QuoteFactory $quoteFactory,
        CustomerSessionFactory $customerSessionFactory
    ) {
        $this->quoteFactory    = $quoteFactory;
        $this->quoteResource = $quoteResource;
        $this->customerSessionFactory = $customerSessionFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $quoteId = $this->getRequest()->getParam('quote_id');
        if (! $quoteId) {
            return $this->_redirect('');
        }

        $quoteModel = $this->quoteFactory->create();
        $this->quoteResource->load($quoteModel, $quoteId);

        if (! $quoteModel->getId() || ! $quoteModel->getCustomerId()) {
            return $this->_redirect('');
        }

        return $this->handleRequest($quoteModel);
    }

    /**
     * @param Quote $quoteModel
     *
     * @return ResponseInterface
     * @throws NoSuchEntityException
     */
    private function handleRequest(Quote $quoteModel)
    {
        $customerSession = $this->customerSessionFactory->create();
        if ($customerSession->isLoggedIn()) {
            return $this->_redirect($quoteModel->getStore()->getUrl('checkout/cart'));
        } else {
            $customerSession->setBeforeAuthUrl($quoteModel->getStore()->getUrl('checkout/cart'));
            return $this->_redirect($quoteModel->getStore()->getUrl('customer/account/login'));
        }
    }
}
