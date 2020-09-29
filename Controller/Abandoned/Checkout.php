<?php

namespace Apsis\One\Controller\Abandoned;

use Apsis\One\Model\Service\Cart as ApsisCartHelper;
use Apsis\One\Model\Service\Log;
use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory as QuoteFactory;
use Magento\Quote\Model\Quote;
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
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var ApsisCartHelper
     */
    private $apsisCartHelper;

    /**
     * Checkout constructor.
     *
     * @param Context $context
     * @param QuoteResource $quoteResource
     * @param QuoteFactory $quoteFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param Log $log
     * @param ApsisCartHelper $apsisCartHelper
     */
    public function __construct(
        Context $context,
        QuoteResource $quoteResource,
        QuoteFactory $quoteFactory,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        Log $log,
        ApsisCartHelper $apsisCartHelper
    ) {
        $this->apsisCartHelper = $apsisCartHelper;
        $this->log = $log;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResource = $quoteResource;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            $quoteId = $this->getRequest()->getParam('quote_id');
            $token = $this->getRequest()->getParam('token');
            if (empty($quoteId) || empty($token) || ! $this->apsisCartHelper->isClean($token) ||
                empty($ac = $this->apsisCartHelper->getCart($token)) || $ac->getQuoteId() != $quoteId
            ) {
                return $this->_redirect('');
            }

            /** @var Quote $quoteModel */
            $quoteModel = $this->quoteFactory->create();
            $this->quoteResource->load($quoteModel, $quoteId);

            if (! $quoteModel->getId() || ! $quoteModel->hasItems()) {
                return $this->_redirect('');
            }

            if ($quoteModel->getCustomerId()) {
                return $this->handleRequestForRegisteredCustomer($quoteModel);
            } else {
                return $this->handleRequestForGuestCustomer($quoteModel);
            }
        } catch (Exception $e) {
            $this->log->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return $this->_redirect('');
        }
    }

    /**
     * @param Quote $quoteModel
     *
     * @return ResponseInterface
     */
    private function handleRequestForRegisteredCustomer(Quote $quoteModel)
    {
        try {
            if ($this->customerSession->isLoggedIn()) {
                return $this->_redirect($quoteModel->getStore()->getUrl('checkout/cart'));
            } else {
                $this->customerSession->setBeforeAuthUrl($quoteModel->getStore()->getUrl('checkout/cart'));
                return $this->_redirect($quoteModel->getStore()->getUrl('customer/account/login'));
            }
        } catch (Exception $e) {
            $this->log->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return $this->_redirect('');
        }
    }

    /**
     * @param Quote $quoteModel
     *
     * @return ResponseInterface
     */
    private function handleRequestForGuestCustomer(Quote $quoteModel)
    {
        try {
            $quoteModel->setIsActive(1)->setReservedOrderId(null)->removePayment();
            $this->cartRepository->save($quoteModel);
            $this->checkoutSession->replaceQuote($quoteModel)->unsLastRealOrderId();
            return $this->_redirect($quoteModel->getStore()->getUrl('checkout/cart'));
        } catch (Exception $e) {
            $this->log->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return $this->_redirect('');
        }
    }
}
