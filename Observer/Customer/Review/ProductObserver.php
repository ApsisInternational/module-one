<?php

namespace Apsis\One\Observer\Customer\Review;

use Apsis\One\Observer\AbstractObserver;
use Apsis\One\Service\ProfileService;
use Apsis\One\Service\Sub\SubEventService;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Review as ReviewResourceModel;
use Magento\Review\Model\ReviewFactory;
use Throwable;

class ProductObserver extends AbstractObserver
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var ReviewFactory
     */
    private ReviewFactory $reviewFactory;

    /**
     * @var ReviewResourceModel
     */
    private ReviewResourceModel $reviewResourceModel;

    /**
     * @param ProfileService $profileService
     * @param Registry $registry
     * @param CustomerRegistry $customerRegistry
     * @param SubEventService $subEventService
     * @param Session $session
     * @param ProductRepositoryInterface $productRepository
     * @param ReviewFactory $reviewFactory
     * @param ReviewResourceModel $reviewResourceModel
     */
    public function __construct(
        ProfileService $profileService,
        Registry $registry,
        CustomerRegistry $customerRegistry,
        SubEventService $subEventService,
        Session $session,
        ProductRepositoryInterface  $productRepository,
        ReviewFactory $reviewFactory,
        ReviewResourceModel $reviewResourceModel
    ) {
        parent::__construct($profileService, $registry, $customerRegistry, $subEventService, $session);
        $this->reviewFactory = $reviewFactory;
        $this->productRepository = $productRepository;
        $this->reviewResourceModel = $reviewResourceModel;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Review $dataObject */
            $dataObject = $observer->getEvent()->getDataObject();
            if (empty($dataObject) || ! $dataObject->getId() || ! $dataObject->isObjectNew()) {
                return $this;
            }

            $review = $this->getReviewModel();
            $this->reviewResourceModel->load($review, $dataObject->getId());
            if (! $review->getCustomerId() || ! $review->getEntityPkValue() || ! $review->getStoreId()) {
                return $this;
            }

            /** @var MagentoProduct $product */
            $product = $this->getProductById($review->getEntityPkValue(), $review->getStoreId());
            $customer = $this->getCustomer($review->getCustomerId());
            if (empty($product) || ! $product->getId() || empty($customer) || ! $customer->getId()) {
                return $this;
            }

            $profile = $this->profileService
                ->getProfile((int) $customer->getStoreId(), (string) $customer->getEmail(), (int) $customer->getId());
            if ($profile) {
                $this->subEventService->registerProductReviewedEvent(
                    $review,
                    $product,
                    $profile,
                    $customer,
                    $this->profileService
                );
                $profile->setHasDataChanges(true);
                $this->profileService->subProfileService->profileResource->save($profile);
            }
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
        }
        return $this;
    }

    /**
     * @return Review
     */
    private function getReviewModel(): Review
    {
        return $this->reviewFactory->create();
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return bool|ProductInterface
     */
    private function getProductById(int $productId, int $storeId): bool|ProductInterface
    {
        try {
            return $this->productRepository->getById($productId, false, $storeId);
        } catch (Throwable $e) {
            $this->profileService->logError(__METHOD__, $e);
            return false;
        }
    }
}
