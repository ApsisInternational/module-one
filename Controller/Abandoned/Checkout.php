<?php

namespace Apsis\One\Controller\Abandoned;

use Apsis\One\Model\Service\Cart as ApsisCartHelper;
use Apsis\One\Model\Service\Log;
use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class Checkout extends Action
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var ApsisCartHelper
     */
    private $apsisCartHelper;

    /**
     * Checkout constructor.
     *
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param Log $log
     * @param ApsisCartHelper $apsisCartHelper
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        Log $log,
        ApsisCartHelper $apsisCartHelper
    ) {
        $this->apsisCartHelper = $apsisCartHelper;
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
            if (empty($token) || ! $this->apsisCartHelper->isClean($token) ||
                empty($ac = $this->apsisCartHelper->getCart($token))
            ) {
                return $this->_redirect('');
            }

            /** @var Quote $quoteModel */
            $quoteModel = $this->cartRepository->get($ac->getQuoteId());

            if (! $quoteModel->getId() || ! $quoteModel->hasItems()) {
                return $this->_redirect('');
            }

            return $this->handleCartRebuildRequest($quoteModel, $ac);
        } catch (Exception $e) {
            $this->log->logError(__METHOD__, $e);
            return $this->_redirect('');
        }
    }

    /**
     * @param Quote $quoteModel
     * @param DataObject $ac
     *
     * @return ResponseInterface
     */
    private function handleCartRebuildRequest(Quote $quoteModel, DataObject $ac)
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
        } catch (Exception $e) {
            $this->log->logError(__METHOD__, $e);
            return $this->_redirect('');
        }
    }
}
