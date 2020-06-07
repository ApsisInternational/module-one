<?php

namespace Apsis\One\Controller\Abandoned;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\App\Action\Context;
use Apsis\One\Model\AbandonedFactory;
use Magento\Framework\Registry;

class Cart extends Action
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var AbandonedFactory
     */
    private $abandonedFactory;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * Cart constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param JsonFactory $resultJsonFactory
     * @param AbandonedFactory $abandonedFactory
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        JsonFactory $resultJsonFactory,
        AbandonedFactory $abandonedFactory,
        Registry $registry
    ) {
        $this->registry = $registry;
        $this->abandonedFactory = $abandonedFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $token = (string) $this->getRequest()->getParam('token');
        if ($this->apsisCoreHelper->isClean($token) && $cart = $this->abandonedFactory->create()->getCart($token)) {
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
        $output = $this->getRequest()->getParam('output');
        if ($output === 'json') {
            return $this->renderJson((string) $cart->getCartData());
        } elseif ($output === 'html') {
            $this->registry->register('apsis_one_cart', $cart, true);
            return $this->renderHtml();
        } else {
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
        return $resultJson->setJsonData($body);
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
