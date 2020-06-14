<?php

namespace Apsis\One\Observer\Sales\Cart;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Event;
use Apsis\One\Model\EventFactory;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Model\Product;
use Apsis\One\Model\Events\Historical\Carts\Data;

class AddProduct implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var Data
     */
    private $cartData;

    /**
     * AddProduct constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventFactory $eventFactory
     * @param EventResource $eventResource
     * @param ProfileResource $profileResource
     * @param CheckoutSession $checkoutSession
     * @param Data $cartData
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventFactory $eventFactory,
        EventResource $eventResource,
        ProfileResource $profileResource,
        CheckoutSession $checkoutSession,
        Data $cartData
    ) {
        $this->cartData = $cartData;
        $this->checkoutSession = $checkoutSession;
        $this->profileResource = $profileResource;
        $this->eventFactory = $eventFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventResource = $eventResource;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Quote $cart */
            $cart = $this->checkoutSession->getQuote();
            if ($cart->getCustomerIsGuest() || empty($cart->getCustomerId())) {
                return $this;
            }

            /** @var Product $product */
            $product = $observer->getEvent()->getProduct();

            /** @var Item $item */
            $item = $cart->getItemByProduct($product);

            /** @var Profile $profile */
            $profile = $this->apsisCoreHelper->getProfileByEmailAndStoreId(
                $cart->getCustomerEmail(),
                $cart->getStore()->getId()
            );

            if ($this->isOkToProceed($cart->getStore()) && $profile && $item) {
                $eventModel = $this->eventFactory->create()
                    ->setEventType(Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART)
                    ->setEventData(
                        $this->apsisCoreHelper->serialize(
                            $this->cartData->getDataArr($cart, $item, $this->apsisCoreHelper)
                        )
                    )
                    ->setProfileId($profile->getId())
                    ->setCustomerId($cart->getCustomerId())
                    ->setStoreId($cart->getStore()->getId())
                    ->setEmail($cart->getCustomerEmail())
                    ->setStatus(Profile::SYNC_STATUS_PENDING);
                $this->eventResource->save($eventModel);

                $profile->setCustomerSyncStatus(Profile::SYNC_STATUS_PENDING);
                $this->profileResource->save($profile);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
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
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_PRODUCT_CARTED
        );
        return ($account && $event);
    }
}
