<?php

namespace Apsis\One\Controller\Abandoned;

use Magento\Framework\App\Action\Action;
use Zend\Http\PhpEnvironment\Response;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Cart extends Action
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * Cart constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Json $jsonSerializer
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        Json $jsonSerializer
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context);
    }

    /**
     * Abandoned cart json content
     */
    public function execute()
    {
        //authenticate
        if ($this->authenticate()) {
            /** @todo send real cart content */
            $this->sendJsonResponse(['cart_id' => $this->getRequest()->getParam('quote_id')]);
        }
    }

    /**
     * @param array $body
     *
     * @return mixed
     */
    private function sendJsonResponse(array $body)
    {
        return $this->getResponse()
            ->setHeader('Content-type', 'application/javascript', true)
            ->setBody($this->jsonSerializer->serialize($body))
            ->sendResponse();
    }

    /**
     *
     * @return bool
     */
    public function authenticate()
    {
        if (! $this->apsisCoreHelper->authoriseCode($this->getRequest()->getParam('passcode'))) {
            $this->sendResponse(401, '<h1>401 Unauthorized</h1>');
            return false;
        }

        // Check for required params
        if (! $this->getRequest()->getParam('quote_id')) {
            $this->sendResponse(204);
            return false;
        }

        return true;
    }

    /**
     * @param int $code
     * @param string $body
     *
     * @return Response
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

        return $this->getResponse()->sendHeaders();
    }
}
