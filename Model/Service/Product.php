<?php

namespace Apsis\One\Model\Service;

use Magento\Catalog\Model\Product as MagentoProduct;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Catalog\Helper\Image;
use Throwable;

class Product
{
    /**
     * @var Image
     */
    private $imageHelper;

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
    public function getUrl(MagentoProduct $product, ApsisCoreHelper $helper, int $storeId)
    {
        try {
            return $product->getUrlModel()
                ->getUrl(
                    $product,
                    ['_secure' => $helper->isFrontUrlSecure($storeId), '_scope' => $storeId]
                );
        } catch (Throwable $e) {
            $helper->logError(__METHOD__, $e);
            return $helper->getBaseUrl($storeId);
        }
    }

    /**
     * @param MagentoProduct $product
     * @param Core $helper
     * @param int $storeId
     *
     * @return string
     */
    public function getImageUrl(MagentoProduct $product, ApsisCoreHelper $helper, int $storeId)
    {
        try {
            $productImageUrl = $this->imageHelper
                ->init($product, 'product_page_image_large')
                ->getUrl();
            return $helper->isFrontUrlSecure($storeId) && strpos($productImageUrl, 'http:') !== false?
                str_replace('http:', 'https:', $productImageUrl) : $productImageUrl;
        } catch (Throwable $e) {
            $helper->logError(__METHOD__, $e);
            return $helper->getBaseUrl($storeId);
        }
    }

    /**
     * @param Core $helper
     * @param int $storeId
     * @param int $productId
     *
     * @return string
     */
    public function getReviewUrl(ApsisCoreHelper $helper, int $storeId, int $productId)
    {
        try {
            $store = $helper->getStore($storeId);
            if ($store) {
                return $store->getUrl(
                    'review/product/list',
                    ['id' => $productId, '_secure' => $helper->isFrontUrlSecure($storeId)]
                ) . '#reviews';
            }
        } catch (Throwable $e) {
            $helper->logError(__METHOD__, $e);
        }

        return $helper->getBaseUrl($storeId);
    }
}
