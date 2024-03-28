<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Service\BaseService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Throwable;

abstract class AbstractData implements EntityDataInterface
{
    /**
     * @var Product
     */
    protected Product $product;

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
     * @param int $storeId
     * @param BaseService $baseService
     *
     * @return string
     */
    protected function getProductUrl(int $storeId, BaseService $baseService): string
    {
        return $this->product
            ->getUrlModel()
            ->getUrl(
                $this->product,
                ['_secure' => $baseService->isStoreFrontSecure($storeId), '_scope' => $storeId]
            );
    }

    /**
     * @param int $storeId
     * @param BaseService $baseService
     *
     * @return string
     */
    protected function getProductImageUrl(int $storeId, BaseService $baseService): string
    {
        return $this->imageHelper
            ->init($this->product, 'product_page_image_large')
            ->getUrl();
    }

    /**
     * @param AbstractModel $model
     * @param BaseService $baseService
     *
     * @return void
     */
    protected function fetchAndSetProductFromEntity(AbstractModel $model, BaseService $baseService): void
    {
        try {
            $id = $model->getProductId() ? $model->getProductId() : $model->getEntityPkValue();
            $product = $this->productRepository->getById($id, false, $model->getStoreId());
            $this->fetchProduct($product ?? $model);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param AbstractModel $object
     *
     * @return void
     */
    protected function fetchProduct(AbstractModel $object): void
    {
        if ($object instanceof Product) {
            $this->setProduct($object);
        } elseif ($object instanceof OrderItem && $object->getProduct() instanceof Product) {
            $this->setProduct($object->getProduct());
        } elseif ($object instanceof QuoteItem && $object->getProduct() instanceof Product) {
            $product = $object->getProduct()->setStoreId($object->getStoreId());
            $this->setProduct($product);
        }
    }

    /**
     * @param Product $product
     *
     * @return void
     */
    protected function setProduct(Product $product): void
    {
        $this->product = $product;
    }
}
