<?php

namespace Apsis\One\Controller\Frontend\Abandoned;

use Apsis\One\Controller\AbstractAction;
use Apsis\One\Model\AbandonedModel;
use Apsis\One\Model\ResourceModel\Abandoned\AbandonedCollectionFactory;
use Apsis\One\Service\BaseService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Throwable;

class Checkout extends AbstractAction
{
    const VALID_HTTP_METHODS = ['GET', 'HEAD'];

    /**
     * @var AbandonedCollectionFactory
     */
    private AbandonedCollectionFactory $abandonedCollectionFactory;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var RedirectInterface
     */
    protected RedirectInterface $redirect;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param BaseService $service
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     * @param RedirectInterface $redirect
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        BaseService $service,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        AbandonedCollectionFactory $abandonedCollectionFactory,
        RedirectInterface $redirect
    ) {
        $this->redirect = $redirect;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($request, $response, $service);
    }

    /**
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        try {
            $token = $this->getRequest()->getParam('token');
            if (! in_array($this->getRequest()->getMethod(), self::VALID_HTTP_METHODS) || empty($token) ||
                ! $this->service->isClean($token) || empty($ac = $this->getCart($token))
            ) {
                return $this->redirect('');
            }

            /** @var Quote $quoteModel */
            $quoteModel = $this->cartRepository->get($ac->getQuoteId());
            if (! $quoteModel->getId() || ! $quoteModel->hasItems()) {
                return $this->redirect('');
            }

            return $this->handleCartRebuildRequest($quoteModel, $ac);
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
    private function getCart(string $token): AbandonedModel|bool
    {
        return $this->abandonedCollectionFactory->create()
            ->getFirstItemFromCollection('token', $token);
    }

    /**
     * @param Quote $quoteModel
     * @param DataObject $ac
     *
     * @return ResponseInterface
     */
    private function handleCartRebuildRequest(Quote $quoteModel, DataObject $ac): ResponseInterface
    {
        try {
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

            //Log it
            $info = [
                'Status' => 'Successfully rebuilt cart.',
                'Profile Id' => $ac->getProfileId(),
                'Cart Id' => $ac->getQuoteId(),
                'Store Id' => $ac->getStoreId()
            ];
            $this->service->debug(__METHOD__, $info);

            return $this->redirect($quoteModel->getStore()->getUrl('checkout/cart'));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->redirect('');
        }
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
