<?php

namespace Apsis\One\Observer\Customer\Review;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Apsis\One\Model\Service\Event;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Throwable;

class Product implements ObserverInterface
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
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var Event
     */
    private Event $eventService;

    /**
     * @var ReviewFactory
     */
    private ReviewFactory $reviewFactory;

    /**
     * Product constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param ProductRepositoryInterface $productRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Event $eventService
     * @param ReviewFactory $reviewFactory
     */
    public function __construct(
        ApsisLogHelper $apsisLogHelper,
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
        $this->apsisLogHelper = $apsisLogHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Review $reviewObject */
            $dataObject = $observer->getEvent()->getDataObject();
            if (empty($dataObject) || ! $dataObject->getId()) {
                return $this;
            }

            $reviewObject = $this->reviewFactory->create()->load($dataObject->getId());
            if (empty($reviewObject) ||
                ! $reviewObject->getCustomerId() ||
                ! $reviewObject->getEntityPkValue() ||
                ! $reviewObject->getStoreId() ||
                ! $reviewObject->isApproved()
            ) {
                return $this;
            }

            /** @var MagentoProduct $product */
            $product = $this->getProductById($reviewObject->getEntityPkValue(), $reviewObject->getStoreId());
            $customer = $this->customerRepository->getById($reviewObject->getCustomerId());
            if (empty($product) || ! $product->getId() || empty($customer) || ! $customer->getId()) {
                return $this;
            }

            /** @var Profile $profile */
            $profile = $this->profileCollectionFactory->create()->loadByCustomerId($customer->getId());
            if ($profile) {
                $this->eventService->registerProductReviewEvent($reviewObject, $product, $profile, $customer);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return $this;
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
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
