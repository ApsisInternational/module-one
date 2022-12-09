<?php

namespace Apsis\One\Controller\Cart;

use Apsis\One\Model\Service\Log;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Model\ResourceModel\Quote;
use Throwable;

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
     * @var Validator
     */
    private $formKeyValidator;

    /**
     * Updater constructor.
     *
     * @param Context $context
     * @param Validator $formKeyValidator
     * @param Log $log
     * @param Quote $quoteResource
     * @param Session $session
     */
    public function __construct(
        Context $context,
        Validator $formKeyValidator,
        Log $log,
        Quote $quoteResource,
        Session $session
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->log = $log;
        $this->quoteResource = $quoteResource;
        $this->cartSession = $session;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            if ($this->formKeyValidator->validate($this->getRequest()) &&
                $this->getRequest()->isPost() &&
                $this->getRequest()->isAjax() &&
                ! empty($email = $this->getRequest()->getParam('email')) &&
                filter_var($email, FILTER_VALIDATE_EMAIL) &&
                ! empty($quote = $this->cartSession->getQuote()) &&
                $quote->hasItems()
            ) {
                $quote->setCustomerEmail($email);
                $this->quoteResource->save($quote);
            }
        } catch (Throwable $e) {
            $this->log->logError(__METHOD__, $e);
        }
    }
}
