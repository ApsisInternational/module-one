<?php

namespace Apsis\One\Controller\Frontend\Abandoned;

use Apsis\One\Block\Abandoned\CartBlock;
use Apsis\One\Controller\AbstractAction;
use Apsis\One\Model\AbandonedModel;
use Apsis\One\Model\ResourceModel\Abandoned\AbandonedCollectionFactory;
use Apsis\One\Service\BaseService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\LayoutInterface;
use Throwable;

class Cart extends AbstractAction
{
    const VALID_HTTP_METHODS = ['GET', 'HEAD'];

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var ResultInterface
     */
    private ResultInterface $resultRaw;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var AbandonedCollectionFactory
     */
    private AbandonedCollectionFactory $abandonedCollectionFactory;

    /**
     * @var LayoutInterface
     */
    private LayoutInterface $layout;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param BaseService $service
     * @param StoreManagerInterface $storeManager
     * @param JsonFactory $resultJsonFactory
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     * @param LayoutInterface $layout
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        BaseService $service,
        StoreManagerInterface $storeManager,
        JsonFactory $resultJsonFactory,
        AbandonedCollectionFactory $abandonedCollectionFactory,
        LayoutInterface $layout,
        ResultFactory $resultFactory
    ) {
        $this->storeManager = $storeManager;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->layout = $layout;
        $this->resultRaw = $resultFactory->create(ResultFactory::TYPE_RAW);
        parent::__construct($request, $response, $service);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        try {
            //Validate http method against allowed one.
            if (! in_array($this->getRequest()->getMethod(), self::VALID_HTTP_METHODS)) {
                return $this->sendResponse($this->resultRaw, 405);
            }

            $token = (string) $this->getRequest()->getParam('token');
            if (empty($token) || ! $this->service->isClean($token)) {
                return $this->sendResponse($this->resultRaw, 400);
            }

            $cart = $this->getCart($token);
            if (empty($cart) || empty($cart->getCartData()) || ! $this->isJson($cart->getCartData())) {
                return $this->sendResponse($this->resultRaw, 404);
            }

            return $this->renderOutput($cart);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param string $token
     *
     * @return AbandonedModel|bool
     */
    private function getCart(string $token): AbandonedModel|bool
    {
        return $this->abandonedCollectionFactory->create()
            ->getFirstItemFromCollection('token', $token);
    }

    /**
     * @param AbandonedModel $cart
     *
     * @return ResultInterface
     */
    private function renderOutput(AbandonedModel $cart): ResultInterface
    {
        try {
            $cart->setCartData($this->getData($cart->getCartData(), $cart->getStoreId()));
            $output = $this->getRequest()->getParam('output');
            return $output === 'html' ? $this->renderHtml($cart) : $this->renderJson($cart);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param AbandonedModel $cart
     *
     * @return ResultInterface
     */
    private function renderHtml(AbandonedModel $cart): ResultInterface
    {
        try {
            /** @var CartBlock $block */
            $block = $this->layout->createBlock(CartBlock::class)->setCacheable(false);
            $html = $block->setAbandonedModel($cart)
                ->setTemplate('Apsis_One::abandoned/cart.phtml')
                ->toHtml();

            $this->resultRaw
                ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
                ->setContents($html);

            return $this->sendResponse($this->resultRaw, 200);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param AbandonedModel $cart
     *
     * @return ResultInterface
     */
    private function renderJson(AbandonedModel $cart): ResultInterface
    {
        try {
            $resultJson = $this->resultJsonFactory
                ->create()
                ->setJsonData('[' . $cart->getCartData() . ']');
            return $this->sendResponse($resultJson, 200);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param ResultInterface $result
     * @param int $code
     *
     * @return ResultInterface
     */
    public function sendResponse(ResultInterface $result, int $code): ResultInterface
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
            $this->service->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    private function isJson(string $string): bool
    {
        try {
            json_decode($string);
            return (json_last_error() == JSON_ERROR_NONE);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param string $data
     * @param int $storeId
     *
     * @return string
     */
    private function getData(string $data, int $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $isSecureNeeded = $store->isCurrentlySecure() && $store->isFrontUrlSecure() && str_contains($data, 'http:');
            return $isSecureNeeded ? str_replace('http:', 'https:', $data) : $data;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $data;
        }
    }

    /**
     * @return ResultInterface
     */
    private function handleException(): ResultInterface
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
