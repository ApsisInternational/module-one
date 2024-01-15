<?php

namespace Apsis\One\Observer\Customer\Wishlist;

use Apsis\One\Observer\AbstractObserver;
use Magento\Framework\Event\Observer;
use Magento\Wishlist\Model\Wishlist;
use Throwable;

class AddProductObserver extends AbstractObserver
{
    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Wishlist $wishlist */
            $wishlist = $observer->getEvent()->getWishlist();
            if (empty($wishlist) || ! $wishlist->getCustomerId() || empty($store = $wishlist->getStore())) {
                return $this;
            }

            $customer = $this->getCustomer($wishlist->getCustomerId());
            if (empty($customer) || ! $customer->getId()) {
                return $this;
            }

            $profile = $this->profileService
                ->getProfile((int) $customer->getStoreId(), (string) $customer->getEmail(), (int) $customer->getId());
            if ($profile) {
                $this->subEventService
                    ->registerProductWishedEvent(
                        $wishlist,
                        $observer->getEvent()->getItem(),
                        $observer,
                        $store,
                        $profile,
                        $customer,
                        $this->profileService
                    );
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return $this;
    }
}
