<?php

namespace Apsis\One\Model\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image;

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
     * @param ProductInterface $product
     * @param string $imageId
     *
     * @return string
     */
    public function getProductImageUrl(ProductInterface $product, string $imageId = 'small_image')
    {
        return $this->imageHelper
            ->init($product, $imageId)
            ->setImageFile($product->getSmallImage())
            ->getUrl();
    }
}
