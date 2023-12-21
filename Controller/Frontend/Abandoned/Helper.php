<?php

namespace Apsis\One\Controller\Frontend\Abandoned;

use Apsis\One\Controller\AbstractAction;
use Apsis\One\Service\BaseService;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Model\ResourceModel\Quote;
use Throwable;

class Helper extends AbstractAction
{
    /**
     * @var Quote
     */
    private Quote $quoteResource;

    /**
     * @var Session
     */
    private Session $cartSession;

    /**
     * @var Validator
     */
    private Validator $formKeyValidator;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param BaseService $service
     * @param Validator $formKeyValidator
     * @param Quote $quoteResource
     * @param Session $session
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        BaseService $service,
        Validator $formKeyValidator,
        Quote $quoteResource,
        Session $session
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->quoteResource = $quoteResource;
        $this->cartSession = $session;
        parent::__construct($request, $response, $service);
    }

    /**
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
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
            $this->service->logError(__METHOD__, $e);
        }
        return $this->getResponse();
    }
}
