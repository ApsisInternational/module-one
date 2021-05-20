<?php

namespace Apsis\One\Controller\Cart;

use Apsis\One\Model\Service\Log;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Model\ResourceModel\Quote;

class Updater extends Action
{
    /**
     * @var Quote
     */
    private $quoteResource;

    /**
     * @var Session
     */
    private $cartSession;

    /**
     * @var Log
     */
    private $log;

    /**
     * Updater constructor.
     *
     * @param Log $log
     * @param Quote $quoteResource
     * @param Session $session
     * @param Context $context
     */
    public function __construct(
        Log $log,
        Quote $quoteResource,
        Session $session,
        Context $context
    ) {
        $this->log = $log;
        $this->quoteResource = $quoteResource;
        $this->cartSession = $session;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        try {
            if (! empty($email = $this->getRequest()->getParam('email')) &&
                filter_var($email, FILTER_VALIDATE_EMAIL) && ! empty($quote = $this->cartSession->getQuote()) &&
                $quote->hasItems() && ! $quote->getCustomerEmail()
            ) {
                $quote->setCustomerEmail($email);
                $this->quoteResource->save($quote);
            }
        } catch (Exception $e) {
            $this->log->logError(__METHOD__, $e);
        }
    }
}
