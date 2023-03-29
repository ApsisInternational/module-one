<?php

namespace Apsis\One\Controller\Frontend\Abandoned;

use Apsis\One\Model\Abandoned;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;
use Apsis\One\Model\Service\Log;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Throwable;

class Checkout extends Action
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
     * @var Log
     */
    private Log $log;

    /**
     * Checkout constructor.
     *
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param Log $log
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        Log $log,
        AbandonedCollectionFactory $abandonedCollectionFactory
    ) {
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->log = $log;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            $token = $this->getRequest()->getParam('token');
            if (! in_array($this->getRequest()->getMethod(), self::VALID_HTTP_METHODS) || empty($token) ||
                ! $this->isClean($token) || empty($ac = $this->getCart($token))) {
                return $this->_redirect('');
            }

            /** @var Quote $quoteModel */
            $quoteModel = $this->cartRepository->get($ac->getQuoteId());

            if (! $quoteModel->getId() || ! $quoteModel->hasItems()) {
                return $this->_redirect('');
            }

            return $this->handleCartRebuildRequest($quoteModel, $ac);
        } catch (Throwable $e) {
            $this->log->logError(__METHOD__, $e);
            return $this->_redirect('');
        }
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public function isClean(string $string): bool
    {
        return ! preg_match("/[^a-zA-Z\d-]/i", $string);
    }

    /**
     * @param string $token
     *
     * @return DataObject|Abandoned|bool
     */
    public function getCart(string $token)
    {
        return $this->abandonedCollectionFactory->create()
            ->loadByToken($token);
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
            $this->log->debug(__METHOD__, $info);

            return $this->_redirect($quoteModel->getStore()->getUrl('checkout/cart'));
        } catch (Throwable $e) {
            $this->log->logError(__METHOD__, $e);
            return $this->_redirect('');
        }
    }
}
