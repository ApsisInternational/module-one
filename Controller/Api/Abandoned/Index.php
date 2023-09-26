<?php

namespace Apsis\One\Controller\Api\Abandoned;

use Apsis\One\Controller\AbstractAction;
use Apsis\One\Model\AbandonedModel;
use Apsis\One\Model\ResourceModel\Abandoned\AbandonedCollectionFactory;
use Apsis\One\Service\BaseService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class Index extends AbstractAction
{
    const VALID_HTTP_METHODS = ['GET', 'HEAD'];
    const VALID_ACTIONS = ['getCartContent', 'rebuildCartCheckout'];

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
     * @var RedirectInterface
     */
    private RedirectInterface $redirect;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        BaseService $service,
        StoreManagerInterface $storeManager,
        JsonFactory $resultJsonFactory,
        AbandonedCollectionFactory $abandonedCollectionFactory,
        ResultFactory $resultFactory,
        RedirectInterface $redirect,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
    ) {
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->redirect = $redirect;
        $this->storeManager = $storeManager;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->resultRaw = $resultFactory->create(ResultFactory::TYPE_RAW);
        parent::__construct($request, $response, $service);
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        try {
            if (! in_array($this->getRequest()->getMethod(), self::VALID_HTTP_METHODS)) {
                return $this->sendResponse($this->resultRaw, 405);
            }

            $token = (string) $this->getRequest()->getParam('taskId');
            $method = (string) $this->getRequest()->getParam('actionMethod');
            if (empty($token) || ! $this->service->isClean($token) || ! in_array($method, self::VALID_ACTIONS)) {
                return $this->sendResponse($this->resultRaw, 400);
            }

            $abandonedCart = $this->getAbandonedCart($token);
            if (empty($abandonedCart) || empty($abandonedCart->getCartData())
                || ! $this->isJson($abandonedCart->getCartData())
            ) {
                return $this->sendResponse($this->resultRaw, 404);
            }

            if ($method === 'getCartContent') {
                return $this->getCartContent($abandonedCart);
            }
            return $this->rebuildCartCheckout($abandonedCart->getQuoteId());
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param AbandonedModel $abandonedCart
     *
     * @return ResultInterface
     */
    private function getCartContent(AbandonedModel $abandonedCart): ResultInterface
    {
        try {
            $abandonedCart->setCartData(
                $this->getUpdatedData($abandonedCart->getCartData(), $abandonedCart->getStoreId())
            );
            return $this->sendResponse(
                $this->resultJsonFactory->create()->setJsonData('[' . $abandonedCart->getCartData() . ']'),
                200
            );
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->handleException();
        }
    }

    /**
     * @param int $cartId
     *
     * @return ResponseInterface
     */
    private function rebuildCartCheckout(int $cartId): ResponseInterface
    {
        try {
            /** @var Quote $quoteModel */
            $quoteModel = $this->cartRepository->get($cartId);
            if (! $quoteModel->getId() || ! $quoteModel->hasItems()) {
                return $this->redirect('');
            }

            $quoteModel->setIsActive(1)
                ->setCustomerId(null)
                ->setCustomerGroupId(0)
                ->setCustomerIsGuest(true)
                ->setCheckoutMethod(CartManagementInterface::METHOD_GUEST)
                ->setReservedOrderId(null);
            $quoteModel->getBillingAddress()->setCustomerId(null)->setCustomerAddressId(null)->save();
            $quoteModel->getShippingAddress()->setCustomerId(null)->setCustomerAddressId(null)->save();
            $this->cartRepository->save($quoteModel);
            $this->checkoutSession->replaceQuote($quoteModel)->unsLastRealOrderId();
            return $this->redirect($quoteModel->getStore()->getUrl('checkout/cart'));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->redirect('');
        }
    }

    /**
     * @param string $token
     *
     * @return AbandonedModel|bool
     */
    private function getAbandonedCart(string $token): AbandonedModel|bool
    {
        return $this->abandonedCollectionFactory->create()
            ->getFirstItemFromCollection('token', $token);
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
    private function getUpdatedData(string $data, int $storeId): string
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

    /**
     * @param string $path
     * @param array $arguments
     *
     * @return ResponseInterface
     */
    protected function redirect(string $path, array $arguments = []): ResponseInterface
    {
        $this->redirect->redirect($this->getResponse(), $path, $arguments);
        return $this->getResponse();
    }
}
