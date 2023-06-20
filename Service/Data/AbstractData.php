<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Service\BaseService;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Throwable;

abstract class AbstractData
{
    /**
     * @var Product|null
     */
    protected ?Product $product = null;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var Image
     */
    protected Image $imageHelper;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Image $imageHelper
     */
    public function __construct(ProductRepositoryInterface $productRepository, Image $imageHelper)
    {
        $this->imageHelper = $imageHelper;
        $this->productRepository = $productRepository;
    }

    /**
     * @param AbstractModel $object
     * @param BaseService $baseService
     *
     * @return void
     */
    protected function fetchProduct(AbstractModel $object, BaseService $baseService): void
    {
        try {
            $this->product = null;

            if ($object instanceof Product) {
                $this->setProduct($object, $baseService);
            } elseif ($object instanceof OrderItem && $object->getProduct() instanceof Product) {
                $this->setProduct($object->getProduct(), $baseService);
            } elseif ($object instanceof QuoteItem && $object->getProduct() instanceof Product) {
                $product = $object->getProduct()->setStoreId($object->getStoreId());
                $this->setProduct($product, $baseService);
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            $this->product = null;
        }
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @param BaseService $baseService
     *
     * @return ProductInterface|null
     */
    protected function loadProduct(int $productId, int $storeId, BaseService $baseService): ?ProductInterface
    {
        try {
            return $this->productRepository->getById($productId, false, $storeId);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param Product $product
     * @param BaseService $baseService
     *
     * @return void
     */
    private function setProduct(Product $product, BaseService $baseService): void
    {
        try {
            if ($product->getId()) {
                $this->product = $product;
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            $this->product = null;
        }
    }

    /**
     * @param BaseService $baseService
     *
     * @return bool
     */
    protected function isProductSet(BaseService $baseService): bool
    {
        try {
            return isset($this->product) && $this->product instanceof Product && $this->product->getId();
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param int $storeId
     * @param BaseService $baseService
     *
     * @return string
     */
    protected function getProductUrl(int $storeId, BaseService $baseService): string
    {
        try {
            if ($this->isProductSet($baseService)) {
                return $this->getUrl($this->product, $baseService, $storeId);
            }

            return $baseService->getStoreBaseUrl($storeId);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param int $storeId
     * @param BaseService $baseService
     *
     * @return string
     */
    protected function getProductReviewUrl(int $storeId, BaseService $baseService): string
    {
        try {
            if ($this->isProductSet($baseService)) {
                return $this->getReviewUrl($baseService, $storeId, $this->product->getId());
            }

            return $baseService->getStoreBaseUrl($storeId);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param int $storeId
     * @param BaseService $baseService
     *
     * @return string
     */
    protected function getProductImageUrl(int $storeId, BaseService $baseService): string
    {
        try {
            if ($this->isProductSet($baseService)) {
                return $this->getImageUrl($this->product, $baseService, $storeId);
            }

            return $baseService->getStoreBaseUrl($storeId);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param Product $product
     * @param BaseService $baseService
     * @param int $storeId
     *
     * @return string
     */
    protected function getUrl(Product $product, BaseService $baseService, int $storeId): string
    {
        try {
            return $product->getUrlModel()
                ->getUrl(
                    $product,
                    ['_secure' => $baseService->isStoreFrontSecure($storeId), '_scope' => $storeId]
                );
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return $baseService->getStoreBaseUrl($storeId);
        }
    }

    /**
     * @param Product $product
     * @param BaseService $baseService
     * @param int $storeId
     *
     * @return string
     */
    protected function getImageUrl(Product $product, BaseService $baseService, int $storeId): string
    {
        try {
            $productImageUrl = $this->imageHelper
                ->init($product, 'product_page_image_large')
                ->getUrl();
            return $baseService->isStoreFrontSecure($storeId) && str_contains($productImageUrl, 'http:') ?
                str_replace('http:', 'https:', $productImageUrl) : $productImageUrl;
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return $baseService->getStoreBaseUrl($storeId);
        }
    }

    /**
     * @param BaseService $baseService
     * @param int $storeId
     * @param int $productId
     *
     * @return string
     */
    protected function getReviewUrl(BaseService $baseService, int $storeId, int $productId): string
    {
        try {
            $store = $baseService->getStore($storeId);
            if ($store) {
                return $store->getUrl(
                    'review/product/list',
                    ['id' => $productId, '_secure' => $baseService->isStoreFrontSecure($storeId)]
                ) . '#reviews';
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $baseService->getStoreBaseUrl($storeId);
    }
}
