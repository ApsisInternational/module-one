<?php

namespace Apsis\One\Observer\Customer\Wishlist;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Event;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Wishlist\Model\Wishlist;
use Throwable;

class AddProduct implements ObserverInterface
{
    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $apsisLogHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var Event
     */
    private Event $eventService;

    /**
     * AddProduct constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     */
    public function __construct(
        ApsisLogHelper $apsisLogHelper,
        CustomerRepositoryInterface $customerRepository,
        ProfileCollectionFactory $profileCollectionFactory,
        Event $eventService
    ) {
        $this->eventService = $eventService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->apsisLogHelper = $apsisLogHelper;
    }

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

            $customer = $this->customerRepository->getById($wishlist->getCustomerId());
            if (empty($customer) || ! $customer->getId()) {
                return $this;
            }

            $profile = $this->profileCollectionFactory->create()->loadByCustomerId($customer->getId());
            if ($profile) {
                $this->eventService->registerWishlistEvent($observer, $wishlist, $store, $profile, $customer);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return $this;
    }
}
