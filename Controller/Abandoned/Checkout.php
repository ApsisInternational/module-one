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

class Checkout extends Action
{
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
     * @param QuoteFactory $quoteFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param Log $log
     * @param ApsisCartHelper $apsisCartHelper
     */
    public function __construct(
        Context $context,
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
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            $token = $this->getRequest()->getParam('token');
            if (empty($token) || ! $this->apsisCartHelper->isClean($token) ||
                empty($ac = $this->apsisCartHelper->getCart($token))
            ) {
                return $this->_redirect('');
            }

            /** @var Quote $quoteModel */
            $quoteModel = $this->cartRepository->get($ac->getQuoteId());

            if (! $quoteModel->getId() || ! $quoteModel->hasItems()) {
                return $this->_redirect('');
            }

            return $this->handleCartRebuildRequest($quoteModel);
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
    private function handleCartRebuildRequest(Quote $quoteModel)
    {
        try {
            $quoteModel->setIsActive(1)->setReservedOrderId(null);
            $this->cartRepository->save($quoteModel);
            $this->checkoutSession->replaceQuote($quoteModel)->unsLastRealOrderId();
            return $this->_redirect($quoteModel->getStore()->getUrl('checkout/cart'));
        } catch (Exception $e) {
            $this->log->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return $this->_redirect('');
        }
    }
}
