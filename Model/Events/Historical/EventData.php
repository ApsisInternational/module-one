<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Throwable;

abstract class EventData
{
    /**
     * @var ProductServiceProvider
     */
    protected ProductServiceProvider $productServiceProvider;

    /**
     * @var ApsisCoreHelper
     */
    protected ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var Product|null
     */
    protected ?Product $product = null;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * Data constructor.
     *
     * @param ProductServiceProvider $productServiceProvider
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductServiceProvider $productServiceProvider,
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
        $this->productServiceProvider = $productServiceProvider;
    }

    /**
     * @param AbstractModel $model
     *
     * @return array
     */
    abstract protected function getProcessedDataArr(AbstractModel $model): array;

    /**
     * @param AbstractModel $object
     *
     * @return void
     */
    protected function fetchProduct(AbstractModel $object): void
    {
        try {
            $this->product = null;

            if ($object instanceof Product) {
                $this->setProduct($object);
            } elseif ($object instanceof OrderItem && $object->getProduct() instanceof Product) {
                $this->setProduct($object->getProduct());
            } elseif ($object instanceof QuoteItem && $object->getProduct() instanceof Product) {
                $product = $object->getProduct()->setStoreId($object->getStoreId());
                $this->setProduct($product);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->product = null;
        }
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return ProductInterface|null
     */
    protected function loadProduct(int $productId, int $storeId)
    {
        try {
            return $this->productRepository->getById($productId, false, $storeId);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param Product $product
     *
     * @return void
     */
    private function setProduct(Product $product): void
    {
        try {
            if ($product->getId()) {
                $this->product = $product;
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->product = null;
        }
    }

    /**
     * @return bool
     */
    protected function isProductSet(): bool
    {
        try {
            return isset($this->product) && $this->product instanceof Product && $this->product->getId();
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param int $storeId
     *
     * @return string
     */
    protected function getProductUrl(int $storeId): string
    {
        try {
            if ($this->isProductSet()) {
                return $this->productServiceProvider->getUrl($this->product, $this->apsisCoreHelper, $storeId);
            }

            return $this->apsisCoreHelper->getBaseUrl($storeId);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param int $storeId
     *
     * @return string
     */
    protected function getProductReviewUrl(int $storeId): string
    {
        try {
            if ($this->isProductSet()) {
                return $this->productServiceProvider
                    ->getReviewUrl($this->apsisCoreHelper, $storeId, $this->product->getId());
            }

            return $this->apsisCoreHelper->getBaseUrl($storeId);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param int $storeId
     *
     * @return string
     */
    protected function getProductImageUrl(int $storeId): string
    {
        try {
            if ($this->isProductSet()) {
                return $this->productServiceProvider->getImageUrl($this->product, $this->apsisCoreHelper, $storeId);
            }

            return $this->apsisCoreHelper->getBaseUrl($storeId);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return '';
        }
    }
}
