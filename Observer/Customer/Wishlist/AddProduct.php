<?php

namespace Apsis\One\Observer\Customer\Wishlist;

use Apsis\One\Model\Profile;
use Exception;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\EventFactory;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Event;
use Magento\Store\Model\ScopeInterface;
use Magento\Wishlist\Model\Wishlist;
use Magento\Catalog\Model\Product;
use Magento\Wishlist\Model\Item as WishlistItem;
use Apsis\One\Helper\Config as ApsisConfigHelper;
use Magento\Store\Model\Store;

class AddProduct implements ObserverInterface
{
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
     * AddProduct constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventFactory $eventFactory
     * @param EventResource $eventResource
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventFactory $eventFactory,
        EventResource $eventResource
    ) {
        $this->eventFactory = $eventFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventResource = $eventResource;
    }

    public function execute(Observer $observer)
    {
        /** @var Wishlist $wishlist */
        $wishlist = $observer->getEvent()->getWishlist();
        $store = $wishlist->getStore();
        /** @var Customer $customer */
        $customer = $this->apsisCoreHelper->getCustomerById($wishlist->getCustomerId());

        if ($customer && $this->isOkToProceed($store)) {
            /** @var Product $product */
            $product = $observer->getEvent()->getProduct();
            /** @var WishlistItem $item */
            $item = $observer->getEvent()->getItem();

            $eventModel = $this->eventFactory
                ->create()
                ->setEventType(Event::EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST)
                ->setEventData($this->apsisCoreHelper->serialize($this->getDataArr($wishlist, $store, $item, $product)))
                ->setCustomerId($wishlist->getCustomerId())
                ->setStoreId($store->getId())
                ->setEmail($customer->getEmail())
                ->setStatus(Profile::SYNC_STATUS_PENDING);

            try {
                $this->eventResource->save($eventModel);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
            }
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
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_WISHLIST
        );

        $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED
        );

        return ($account && $event && $sync);
    }

    /**
     * @param Wishlist $wishlist
     * @param Store $store
     * @param WishlistItem $item
     * @param Product $product
     *
     * @return array
     */
    private function getDataArr(Wishlist $wishlist, Store $store, WishlistItem $item, Product $product)
    {
        $data = [
            'wishlist_id' => (int) $wishlist->getId(),
            'wishlist_item_id' => (int) $item->getId(),
            'wishlist_name' => (string) $wishlist->getName(),
            'customer_id' => (int) $wishlist->getCustomerId(),
            'website_name' => (string) $this->apsisCoreHelper->getWebsiteNameFromStoreId($store->getId()),
            'store_name' => (string) $this->apsisCoreHelper->getStoreNameFromId($store->getId()),
            'added_at' => (string) $this->apsisCoreHelper->formatDateForPlatformCompatibility($item->getAddedAt()),
            'product_id' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'product_url' => (string) $product->getProductUrl(),
            'product_image_url' => (string) $this->apsisCoreHelper->getProductImageUrl($product),
            'catalog_price_amount' => (float) $this->apsisCoreHelper->round($product->getPrice()),
            'qty' => (float) $item->getQty(),
            'currency_code' => (string) $store->getCurrentCurrencyCode(),
        ];
        return $data;
    }
}
