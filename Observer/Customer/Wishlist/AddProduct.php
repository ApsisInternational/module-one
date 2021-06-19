<?php

namespace Apsis\One\Observer\Customer\Wishlist;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Event;
use Throwable;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Wishlist\Model\Wishlist;

class AddProduct implements ObserverInterface
{
    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Event
     */
    private $eventService;

    /**
     * AddProduct constructor.
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        CustomerRepositoryInterface $customerRepository,
        ProfileCollectionFactory $profileCollectionFactory,
        Event $eventService
    ) {
        $this->eventService = $eventService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Wishlist $wishlist */
            $wishlist = $observer->getEvent()->getWishlist();
            if (empty($wishlist) || ! $wishlist->getCustomerId() || empty($store = $wishlist->getStore()) ||
                ! $this->isOkToProceed($store)
            ) {
                return $this;
            }

            $customer = $this->customerRepository->getById($wishlist->getCustomerId());
            if (empty($customer) || ! $customer->getId()) {
                return $this;
            }

            $profile = $this->profileCollectionFactory->create()->loadByCustomerId($customer->getId());
            if ($profile) {
                $this->eventService->registerWishlistEvent($observer, $wishlist, $store, $profile, $customer);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $this;
    }

    /**
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isOkToProceed(StoreInterface $store)
    {
        $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());
        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::EVENTS_CUSTOMER_WISHLIST
        );
        return ($account && $event);
    }
}
