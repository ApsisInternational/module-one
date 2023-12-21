<?php

namespace Apsis\One\Service\Data\Cart;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Throwable;

class CartData extends AbstractData
{
    /**
     * @param ProfileModel $profile
     * @param Quote $quote
     * @param Item $item
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getCartedData(ProfileModel $profile, Quote $quote, Item $item, BaseService $baseService): array
    {
        try {
            $this->fetchAndSetProductFromItem($item, $baseService);
            $commonDataArray = $this->getCommonProdDataArray($profile, $quote->getStoreId(), $baseService);
            if (empty($commonDataArray)) {
                return [];
            }

            return array_merge(
                $commonDataArray,
                [
                    'product_quantity' => $item->getQty() ? (float) $item->getQty() :
                        (float) ($item->getQtyOrdered() ? $item->getQtyOrdered() : 1)
                ]
            );
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
