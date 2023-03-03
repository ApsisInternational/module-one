<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as MagentoProduct;
use Throwable;

class Product
{
    /**
     * @var Image
     */
    private Image $imageHelper;

    /**
     * Product constructor.
     *
     * @param Image $imageHelper
     */
    public function __construct(Image $imageHelper)
    {
        $this->imageHelper = $imageHelper;
    }

    /**
     * @param MagentoProduct $product
     * @param ApsisCoreHelper $helper
     * @param int $storeId
     *
     * @return string
     */
    public function getUrl(MagentoProduct $product, ApsisCoreHelper $helper, int $storeId): string
    {
        try {
            return $product->getUrlModel()
                ->getUrl(
                    $product,
                    ['_secure' => $helper->isStoreFrontSecure($storeId), '_scope' => $storeId]
                );
        } catch (Throwable $e) {
            $helper->logError(__METHOD__, $e);
            return $helper->getStoreBaseUrl($storeId);
        }
    }

    /**
     * @param MagentoProduct $product
     * @param Core $helper
     * @param int $storeId
     *
     * @return string
     */
    public function getImageUrl(MagentoProduct $product, ApsisCoreHelper $helper, int $storeId): string
    {
        try {
            $productImageUrl = $this->imageHelper
                ->init($product, 'product_page_image_large')
                ->getUrl();
            return $helper->isStoreFrontSecure($storeId) && str_contains($productImageUrl, 'http:') ?
                str_replace('http:', 'https:', $productImageUrl) : $productImageUrl;
        } catch (Throwable $e) {
            $helper->logError(__METHOD__, $e);
            return $helper->getStoreBaseUrl($storeId);
        }
    }

    /**
     * @param Core $helper
     * @param int $storeId
     * @param int $productId
     *
     * @return string
     */
    public function getReviewUrl(ApsisCoreHelper $helper, int $storeId, int $productId): string
    {
        try {
            $store = $helper->getStore($storeId);
            if ($store) {
                return $store->getUrl(
                    'review/product/list',
                    ['id' => $productId, '_secure' => $helper->isStoreFrontSecure($storeId)]
                ) . '#reviews';
            }
        } catch (Throwable $e) {
            $helper->logError(__METHOD__, $e);
        }

        return $helper->getStoreBaseUrl($storeId);
    }
}
