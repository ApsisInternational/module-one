<?php

namespace Apsis\One\Observer\Customer\Wishlist;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Event;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Wishlist\Model\Wishlist;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;

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
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Wishlist $wishlist */
            $wishlist = $observer->getEvent()->getWishlist();
            $store = $wishlist->getStore();
            $customer = $this->customerRepository->getById($wishlist->getCustomerId());
            $profile = $this->profileCollectionFactory->create()
                ->loadByEmailAndStoreId($customer->getEmail(), $store->getId());

            if ($customer && $this->isOkToProceed($store) && $profile) {
                $this->eventService->registerWishlistEvent($observer, $wishlist, $store, $profile, $customer);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_WISHLIST
        );
        return ($account && $event);
    }
}
