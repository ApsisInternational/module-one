<?php

namespace Apsis\One\Controller\Abandoned;

use Apsis\One\Model\Service\Cart as ApsisCartHelper;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;

class Cart extends Action
{
    const REGISTRY_NAME = 'apsis_one_cart';
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ApsisCartHelper
     */
    private $apsisCartHelper;

    /**
     * Cart constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Registry $registry
     * @param ApsisCartHelper $apsisCartHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Registry $registry,
        ApsisCartHelper $apsisCartHelper
    ) {
        $this->apsisCartHelper = $apsisCartHelper;
        $this->registry = $registry;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $token = (string) $this->getRequest()->getParam('token');
        if ($this->apsisCartHelper->isClean($token) && $cart = $this->apsisCartHelper->getCart($token)) {
            return (strlen($cart->getCartData())) ? $this->renderOutput($cart) : $this->sendResponse(204);
        } else {
            return $this->sendResponse(401, '401 Unauthorized');
        }
    }

    /**
     * @param DataObject $cart
     *
     * @return ResponseInterface|Json
     */
    private function renderOutput(DataObject $cart)
    {
        switch ($this->getRequest()->getParam('output')) {
            case 'json':
                return $this->renderJson((string) $cart->getCartData());
            case 'html':
                $this->registry->unregister(self::REGISTRY_NAME);
                $this->registry->register(self::REGISTRY_NAME, $cart, true);
                return $this->renderHtml();
            default:
                return $this->sendResponse(204);
        }
    }

    /**
     * @return ResponseInterface
     */
    private function renderHtml()
    {
        $this->_view->loadLayout();
        $this->_view->renderLayout();
        return $this->getResponse();
    }

    /**
     * @param string $body
     *
     * @return Json
     */
    private function renderJson(string $body)
    {
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setJsonData('[' . $body . ']');
    }

    /**
     * @param int $code
     * @param string $body
     *
     * @return ResponseInterface
     */
    public function sendResponse(int $code, string $body = '')
    {
        $this->getResponse()
            ->setHttpResponseCode($code)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', 'text/html; charset=UTF-8', true);

        if (strlen($body)) {
            $this->getResponse()->setBody($body);
        }

        return $this->getResponse();
    }
}
