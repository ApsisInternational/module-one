<?php

namespace Apsis\One\Controller\Abandoned;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Zend\Http\PhpEnvironment\Response;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\App\Action\Context;
use Apsis\One\Model\AbandonedFactory;

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
    protected $resultJsonFactory;

    /**
     * Cart constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param JsonFactory $resultJsonFactory
     * @param AbandonedFactory $abandonedFactory
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        JsonFactory $resultJsonFactory,
        AbandonedFactory $abandonedFactory
    ) {
        $this->abandonedFactory = $abandonedFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface|Response
     */
    public function execute()
    {
        $quoteId = (int) $this->getRequest()->getParam('quote_id');
        $token = $this->getRequest()->getParam('token');
        if (strlen($token) && $this->apsisCoreHelper->isEnabledForSelectedScopeInAdmin() && $quoteId) {
            $cartData = $this->abandonedFactory
                ->create()
                ->getCartJsonData($quoteId, $token);

            return (! empty($cartData)) ? $this->sendJsonResponse($cartData) : $this->sendResponse(204);
        } else {
            return $this->sendResponse(401, '<h1>401 Unauthorized</h1>');
        }
    }

    /**
     * @param string $body
     *
     * @return Json
     */
    private function sendJsonResponse(string $body)
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
            $this->getResponse()->setBody('<h1>401 Unauthorized</h1>');
        }

        return $this->getResponse();
    }
}
