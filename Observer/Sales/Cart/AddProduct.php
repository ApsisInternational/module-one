<?php

namespace Apsis\One\Observer\Sales\Cart;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Event;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;
use Throwable;

class AddProduct implements ObserverInterface
{
    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var CheckoutSession
     */
    protected CheckoutSession $checkoutSession;

    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $apsisLogHelper;

    /**
     * @var Event
     */
    private Event $eventService;

    /**
     * AddProduct constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param CheckoutSession $checkoutSession
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     */
    public function __construct(
        ApsisLogHelper $apsisLogHelper,
        CheckoutSession $checkoutSession,
        ProfileCollectionFactory $profileCollectionFactory,
        Event $eventService
    ) {
        $this->eventService = $eventService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->apsisLogHelper = $apsisLogHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            $cart = $this->checkoutSession->getQuote();
            if (empty($cart) || $cart->getCustomerIsGuest() || ! $cart->getCustomerId()) {
                return $this;
            }

            /** @var Product $product */
            $product = $observer->getEvent()->getProduct();
            if (empty($product) || ! $product->getId()) {
                return $this;
            }

            /** @var Item $item */
            $item = $cart->getItemByProduct($product);
            if (empty($item) || ! $item->getId()) {
                return $this;
            }

            /** @var Profile $profile */
            $profile = $this->profileCollectionFactory->create()->loadByCustomerId($cart->getCustomerId());

            if ($profile) {
                $this->eventService->registerProductCartedEvent($cart, $item, $profile);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }

        return $this;
    }
}
