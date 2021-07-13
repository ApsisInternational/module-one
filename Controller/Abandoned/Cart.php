<?php

namespace Apsis\One\Controller\Abandoned;

use Apsis\One\Model\Service\Cart as ApsisCartHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Block\Cart as CartBlock;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Cart constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param JsonFactory $resultJsonFactory
     * @param ApsisCartHelper $apsisCartHelper
     * @param ApsisLogHelper $apsisLogHelper
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        JsonFactory $resultJsonFactory,
        ApsisCartHelper $apsisCartHelper,
        ApsisLogHelper $apsisLogHelper
    ) {
        parent::__construct($context);

        $this->storeManager = $storeManager;
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
        try {
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
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param DataObject $cart
     *
     * @return ResultInterface
     */
    private function renderOutput(DataObject $cart)
    {
        try {
            $cart->setCartData($this->getData($cart->getCartData(), $cart->getStoreId()));
            $output = $this->getRequest()->getParam('output');
            switch ($output) {
                case 'html':
                    return $this->renderHtml($cart);
                default:
                    return $this->renderJson($cart);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->handleException();
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
                ->setContents($html);

            return $this->sendResponse($this->resultRaw, 200);
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->handleException();
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
                ->setJsonData('[' . $cart->getCartData() . ']');
            return $this->sendResponse($resultJson, 200);
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->handleException();
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
        try {
            return $result->setHttpResponseCode($code)
                ->setHeader('Pragma', 'public', true)
                ->setHeader(
                    'Cache-Control',
                    'no-store, no-cache, must-revalidate, max-age=0',
                    true
                );
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    private function isJson(string $string)
    {
        try {
            json_decode($string);
            return (json_last_error() == JSON_ERROR_NONE);
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param string $data
     * @param int $storeId
     *
     * @return string
     */
    private function getData(string $data, int $storeId)
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $isSecureNeeded = $store->isCurrentlySecure() && $store->isFrontUrlSecure() && str_contains($data, 'http:');
            return $isSecureNeeded ? str_replace('http:', 'https:', $data) : $data;
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $data;
        }
    }

    /**
     * @return ResultInterface
     */
    private function handleException()
    {
        return $this->resultRaw->setHttpResponseCode(500)
            ->setHeader('Pragma', 'public', true)
            ->setHeader(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0',
                true
            );
    }
}
