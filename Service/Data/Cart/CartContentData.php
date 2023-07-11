<?php

namespace Apsis\One\Service\Data\Cart;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Service\AbandonedService;
use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\App\EmulationFactory;
use Throwable;

class CartContentData extends AbstractData
{
    /**
     * @var EmulationFactory
     */
    private EmulationFactory $emulationFactory;

    /**
     * @var CartTotalRepositoryInterface
     */
    private CartTotalRepositoryInterface $cartTotalRepository;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Image $imageHelper
     * @param CollectionFactory $categoryCollection
     * @param EmulationFactory $emulationFactory
     * @param CartTotalRepositoryInterface $cartTotalRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Image $imageHelper,
        CollectionFactory $categoryCollection,
        EmulationFactory $emulationFactory,
        CartTotalRepositoryInterface $cartTotalRepository
    ) {
        parent::__construct($productRepository, $imageHelper, $categoryCollection);
        $this->cartTotalRepository = $cartTotalRepository;
        $this->emulationFactory = $emulationFactory;
    }

    /**
     * @return Emulation
     */
    private function getEmulationModel(): Emulation
    {
        return $this->emulationFactory->create();
    }

    /**
     * @param Quote $quoteModel
     * @param ProfileModel $profileModel
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getCartData(Quote $quoteModel, ProfileModel $profileModel, BaseService $baseService): array
    {
        $appEmulation = $this->getEmulationModel();
        $data = [];

        try {
            $appEmulation->startEnvironmentEmulation($quoteModel->getStoreId(), Area::AREA_FRONTEND, true);

            $shopCurrency = $shopName = null;
            foreach ($quoteModel->getAllVisibleItems() as $quoteItem) {
                $this->fetchAndSetProductFromItem($quoteItem, $baseService);
                $productDataArr = $this->getCommonProdDataArray($profileModel, $quoteItem->getStoreId(), $baseService);
                if (empty($productDataArr)) {
                    $appEmulation->stopEnvironmentEmulation();
                    return $data;
                }

                if (! isset($shopCurrency) || ! isset($shopName)) {
                    $shopCurrency = $productDataArr['shop_currency'];
                    $shopName = $productDataArr['shop_name'];
                }

                $productDataArr['product_quantity'] = $quoteItem->getQty() ?
                    (float) $quoteItem->getQty() :
                    (float) ($quoteItem->getQtyOrdered() ? $quoteItem->getQtyOrdered() : 0);
                $productDataArr['cart_id'] = (string) $quoteModel->getId();

                $cartItemsDataEvent[] = $productDataArr;
                unset(
                    $productDataArr['cart_id'],
                    $productDataArr['profile_id'],
                    $productDataArr['shop_currency'],
                    $productDataArr['shop_name'],
                    $productDataArr['shop_id']
                );
                $cartItemsData[] = $productDataArr;
            }

            if (isset($cartItemsData) && isset($cartItemsDataEvent)) {
                $cartDataEvent = $cartData = [
                    'cart_id' => (string) $quoteModel->getId(),
                    'profile_id' => (string) $profileModel->getId(),
                    'grand_total' => (float) round($quoteModel->getGrandTotal(), 2),
                    'total_products' => (integer) round($quoteModel->getItemsCount(), 2),
                    'total_quantity' => (float)  round(
                        $this->cartTotalRepository->get($quoteModel->getId())->getItemsQty(),
                        2
                    ),
                    'shop_currency' => $shopCurrency,
                    'shop_name' => $shopName,
                    'shop_id' => (string) $quoteModel->getStoreId(),
                    'token' => BaseService::generateUniversallyUniqueIdentifier()
                ];

                $cartData['items'] = $cartItemsData;
                $secure = $baseService->isStoreFrontSecure($quoteModel->getStoreId());
                $cartData['recreate_session_endpoint'] = $quoteModel->getStore()->getUrl(
                    AbandonedService::CHECKOUT_ENDPOINT,
                    ['token' => $cartData['token'], '_secure' => $secure]
                );

                $cartDataEvent['items'] = $cartItemsDataEvent;
                $cartDataEvent['cart_data_endpoint'] = $quoteModel->getStore()->getUrl(
                    AbandonedService::CART_CONTENT_ENDPOINT,
                    ['token' => $cartDataEvent['token'], '_secure' => $secure]
                );

                $data['cart_content'] = $cartData;
                $data['cart_event'] = $cartDataEvent;
            }

            $appEmulation->stopEnvironmentEmulation();
        } catch (Throwable $e) {
            $appEmulation->stopEnvironmentEmulation();
            $baseService->logError(__METHOD__, $e);
        }
        return $data;
    }
}
