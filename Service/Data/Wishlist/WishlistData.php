<?php

namespace Apsis\One\Service\Data\Wishlist;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\BaseService;
use Apsis\One\Service\Data\AbstractData;
use Magento\Catalog\Model\Product;
use Throwable;

class WishlistData extends AbstractData
{
    /**
     * @param ProfileModel $profile
     * @param int $storeId
     * @param Product $product
     * @param BaseService $service
     *
     * @return array
     */
    public function getWishedData(ProfileModel $profile, int $storeId, Product $product, BaseService $service): array
    {
        try {
            $this->fetchProduct($product, $service);
            return $this->getCommonProdDataArray($profile, $storeId, $service);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return [];
        }
    }
}
