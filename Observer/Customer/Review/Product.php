<?php

namespace Apsis\One\Observer\Customer\Review;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Event;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Catalog\Model\Product as MagentoProduct;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Review\Model\ReviewFactory;

class Product implements ObserverInterface
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
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Event
     */
    private $eventService;

    /**
     * @var ReviewFactory
     */
    private $reviewFactory;

    /**
     * Product constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileResource $profileResource
     * @param ProductRepositoryInterface $productRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     * @param ReviewFactory $reviewFactory
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileResource $profileResource,
        ProductRepositoryInterface $productRepository,
        CustomerRepositoryInterface $customerRepository,
        ProfileCollectionFactory $profileCollectionFactory,
        Event $eventService,
        ReviewFactory $reviewFactory
    ) {
        $this->reviewFactory = $reviewFactory;
        $this->eventService = $eventService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->profileResource = $profileResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Review $reviewObject */
            $dataObject = $observer->getEvent()->getDataObject();
            $reviewObject = $this->reviewFactory->create()->load($dataObject->getId());

            if (empty($reviewObject->getCustomerId())) {
                return $this;
            }

            /** @var MagentoProduct $product */
            $product = $this->getProductById($reviewObject->getEntityPkValue(), $reviewObject->getStoreId());
            $customer = $this->customerRepository->getById($reviewObject->getCustomerId());
            if (empty($customer->getId())) {
                return $this;
            }

            /** @var Profile $profile */
            $profile = $this->profileCollectionFactory->create()
                ->loadByCustomerId($customer->getId());

            if ($customer && $product && $this->isOkToProceed($reviewObject->getStoreId()) && $profile &&
                $reviewObject->isApproved()
            ) {
                $this->eventService->registerProductReviewEvent($reviewObject, $product, $profile, $customer);
                $profile->setCustomerSyncStatus(Profile::SYNC_STATUS_PENDING);
                $this->profileResource->save($profile);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $this;
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    private function isOkToProceed(int $storeId)
    {
        $store = $this->apsisCoreHelper->getStore($storeId);
        $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());
        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_REVIEW
        );

        return ($account && $event);
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return bool|ProductInterface
     */
    private function getProductById(int $productId, int $storeId)
    {
        try {
            return $this->productRepository->getById($productId, false, $storeId);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
