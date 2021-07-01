<?php

namespace Apsis\One\Controller\Abandoned;

use Apsis\One\Model\Service\Cart as ApsisCartHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Block\Cart as CartBlock;
use Throwable;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;

class Cart extends Action
{
    const VALID_HTTP_METHODS = ['GET', 'HEAD'];

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var ApsisCartHelper
     */
    private $apsisCartHelper;

    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * @var Raw
     */
    private $resultRaw;

    /**
     * Cart constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ApsisCartHelper $apsisCartHelper
     * @param ApsisLogHelper $apsisLogHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ApsisCartHelper $apsisCartHelper,
        ApsisLogHelper $apsisLogHelper
    ) {
        parent::__construct($context);

        $this->apsisCartHelper = $apsisCartHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->apsisLogHelper = $apsisLogHelper;
        $this->resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        //Validate http method against allowed one.
        if (! in_array($_SERVER['REQUEST_METHOD'], self::VALID_HTTP_METHODS)) {
            return $this->sendResponse($this->resultRaw, 405);
        }

        $token = (string) $this->getRequest()->getParam('token');
        if (empty($token) || ! $this->apsisCartHelper->isClean($token)) {
            return $this->sendResponse($this->resultRaw, 400);
        }

        $cart = $this->apsisCartHelper->getCart($token);
        if (empty($cart) || empty($cart->getCartData()) || ! $this->isJson($cart->getCartData())) {
            return $this->sendResponse($this->resultRaw, 404);
        }

        return $this->renderOutput($cart);
    }

    /**
     * @param DataObject $cart
     *
     * @return ResultInterface
     */
    private function renderOutput(DataObject $cart)
    {
        $output = $this->getRequest()->getParam('output');
        switch ($output) {
            case 'html':
                return $this->renderHtml($cart);
            default:
                return $this->renderJson($cart);
        }
    }


    /**
     * @param DataObject $cart
     *
     * @return ResultInterface
     */
    private function renderHtml(DataObject $cart)
    {
        try {
            /** @var CartBlock $block */
            $block = $this->_view->getLayout()->createBlock(CartBlock::class)->setCacheable(false);
            $html = $block->setCart($cart)
                ->setTemplate('Apsis_One::cart.phtml')
                ->toHtml();

            $this->resultRaw
                ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
                ->setHeader('Apsis-Content-Length', strlen($html) , true)
                ->setContents($html);

            return $this->sendResponse($this->resultRaw, 200);
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->sendResponse($this->resultRaw, 500);
        }
    }

    /**
     * @param DataObject $cart
     *
     * @return ResultInterface
     */
    private function renderJson(DataObject $cart)
    {
        try {
            $resultJson = $this->resultJsonFactory
                ->create()
                ->setHeader('Apsis-Content-Length', strlen($cart->getCartData()) , true)
                ->setJsonData('[' . $cart->getCartData() . ']');
            return $this->sendResponse($resultJson, 200);
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->sendResponse($this->resultRaw, 500);
        }
    }

    /**
     * @param ResultInterface $result
     * @param int $code
     *
     * @return ResultInterface
     */
    public function sendResponse(ResultInterface $result, int $code)
    {
        return $result->setHttpResponseCode($code)
            ->setHeader('Pragma', 'public', true)
            ->setHeader(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0',
                true
            );
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    private function isJson(string $string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
