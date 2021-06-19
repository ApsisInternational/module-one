<?php

namespace Apsis\One\Observer\Sales\Cart;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\Event;
use Throwable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Model\Product;

class AddProduct implements ObserverInterface
{
    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var Event
     */
    private $eventService;

    /**
     * AddProduct constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileResource $profileResource
     * @param CheckoutSession $checkoutSession
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileResource $profileResource,
        CheckoutSession $checkoutSession,
        ProfileCollectionFactory $profileCollectionFactory,
        Event $eventService
    ) {
        $this->eventService = $eventService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->profileResource = $profileResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            $cart = $this->checkoutSession->getQuote();
            if (empty($cart) || $cart->getCustomerIsGuest() || ! $cart->getCustomerId() ||
                ! $this->isOkToProceed($cart->getStore())
            ) {
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
                $profile->setCustomerSyncStatus(Profile::SYNC_STATUS_PENDING);
                $this->profileResource->save($profile);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $this;
    }

    /**
     * @param Store $store
     *
     * @return bool
     */
    private function isOkToProceed(Store $store)
    {
        $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());
        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::EVENTS_PRODUCT_CARTED
        );
        return ($account && $event);
    }
}
