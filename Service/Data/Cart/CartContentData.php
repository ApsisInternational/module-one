<?php

namespace Apsis\One\Service\Data\Cart;

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
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Catalog\Model\Product;
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
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getCartData(Quote $quoteModel, BaseService $baseService): array
    {
        $appEmulation = $this->getEmulationModel();
        $data = [];

        try {
            $appEmulation->startEnvironmentEmulation($quoteModel->getStoreId(), Area::AREA_FRONTEND, true);
            $cartData = $this->getCartDataArr($quoteModel, $baseService);
            if (! empty($cartData)) {
                $data['token'] = BaseService::generateUniversallyUniqueIdentifier();
                $data['cart_content'] = $cartData;
                $data['cart_event'] = $this->getDataForEventFromAcData($cartData, $data['token'], $baseService);
            }
            $appEmulation->stopEnvironmentEmulation();
        } catch (Throwable $e) {
            $appEmulation->stopEnvironmentEmulation();
            $baseService->logError(__METHOD__, $e);
        }
        return $data;
    }

    /**
     * @param Quote $quoteModel
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getCartDataArr(Quote $quoteModel, BaseService $baseService): array
    {
        $cartData = [];
        try {
            $cartData = $this->getMainCartData($quoteModel, $baseService);
            $cartData['items'] = $this->getItemData($quoteModel->getAllVisibleItems(), $baseService);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return $cartData;
    }

    /**
     * @param array $acData
     * @param string $token
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getDataForEventFromAcData(array $acData, string $token, BaseService $baseService): array
    {
        try {
            $items = [];

            foreach ($acData['items'] as $item) {
                $items [] = [
                    'cartId' => $acData['cart_id'],
                    'productId' => $item['product_id'],
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'productUrl' => $item['product_url'],
                    'productImageUrl' => $item['product_image_url'],
                    'qtyOrdered' => $item['qty_ordered'],
                    'priceAmount' => $item['price_amount'],
                    'rowTotalAmount' => $item['row_total_amount'],
                ];
            }

            if (empty($items)) {
                return [];
            }

            return [
                'cartId' => $acData['cart_id'],
                'customerId' => $acData['customer_info']['customer_id'],
                'storeName' => $acData['store_name'],
                'websiteName' => $acData['website_name'],
                'grandTotalAmount' => $acData['grand_total_amount'],
                'itemsCount' => $acData['items_count'],
                'currencyCode' => $acData['currency_code'],
                'token' => $token,
                'items' => $items
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param Quote $quoteModel
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getMainCartData(Quote $quoteModel, BaseService $baseService): array
    {
        try {
            $totals = $this->cartTotalRepository->get($quoteModel->getId());
            return [
                'cart_id' => (int) $quoteModel->getId(),
                'created_at' => (int) $baseService->formatDateForPlatformCompatibility($quoteModel->getCreatedAt()),
                'updated_at' => (int) $baseService->formatDateForPlatformCompatibility($quoteModel->getUpdatedAt()),
                'store_name' => (string) $quoteModel->getStore()->getName(),
                'website_name' => (string) $quoteModel->getStore()->getWebsite()->getName(),
                'subtotal_amount' => (float) round($totals->getSubtotal(), 2),
                'grand_total_amount' => (float) round($quoteModel->getGrandTotal(), 2),
                'tax_amount' => (float) round($totals->getTaxAmount(), 2),
                'shipping_amount' => (float) round($totals->getShippingAmount(), 2),
                'discount_amount' => (float) round($totals->getDiscountAmount(), 2),
                'items_quantity' => (float) round($totals->getItemsQty(), 2),
                'items_count' => (float) round($quoteModel->getItemsCount(), 2),
                'payment_method_title' => (string) $quoteModel->getPayment()->getMethod(),
                'shipping_method_title' => (string) $quoteModel->getShippingAddress()->getShippingDescription(),
                'currency_code' => (string) $totals->getQuoteCurrencyCode(),
                'customer_info' => $this->getCustomerInformation($quoteModel, $baseService),
                'shipping_billing_same' => (boolean) $quoteModel->getShippingAddress()->getSameAsBilling(),
                'shipping_address' => $this->getAddress($quoteModel->getShippingAddress(), $baseService),
                'billing_address' => $this->getAddress($quoteModel->getBillingAddress(), $baseService),
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Quote $quoteModel
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getCustomerInformation(Quote $quoteModel, BaseService $baseService): array
    {
        try {
            return [
                'customer_id' => (int) $quoteModel->getCustomerId(),
                'is_guest' => (boolean) $quoteModel->getCustomerIsGuest(),
                'email' => (string) $quoteModel->getCustomerEmail(),
                'prefix' => (string) $quoteModel->getCustomerPrefix(),
                'suffix' => (string) $quoteModel->getCustomerSuffix(),
                'first_name' => (string) $quoteModel->getCustomerFirstname(),
                'middle_name' => (string) $quoteModel->getCustomerMiddlename(),
                'last_name' => (string) $quoteModel->getCustomerLastname()
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Address $address
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getAddress(Address $address, BaseService $baseService): array
    {
        try {
            return [
                'prefix' => (string) $address->getPrefix(),
                'suffix' => (string) $address->getSuffix(),
                'first_name' => (string) $address->getFirstname(),
                'middle_name' => (string) $address->getMiddlename(),
                'last_name' => (string) $address->getLastname(),
                'company' => (string) $address->getCompany(),
                'street_line_1' => (string) $address->getStreetLine(1),
                'street_line_2' => (string) $address->getStreetLine(2),
                'city' => (string) $address->getCity(),
                'region' => (string) $address->getRegion(),
                'postcode' => (string) $address->getPostcode(),
                'country' => (string) $address->getCountry(),
                'telephone' => (string) $address->getTelephone()
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param array $quoteItems
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getItemData(array $quoteItems, BaseService $baseService): array
    {
        try {
            $itemsData = [];

            /** @var Item $quoteItem */
            foreach ($quoteItems as $quoteItem) {
                $itemsData[] = $this->getItemsData($quoteItem, $baseService);
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $itemsData;
    }

    /**
     * @param Item $quoteItem
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getItemsData(Item $quoteItem, BaseService $baseService): array
    {
        try {
            $this->fetchAndSetProductFromItem($quoteItem, $baseService);
            return [
                'product_id' => (int) $quoteItem->getProductId(),
                'sku' => (string) $quoteItem->getSku(),
                'name' => (string) $quoteItem->getName(),
                'product_url' => (string) $this->getProductUrl($quoteItem->getStoreId(), $baseService),
                'product_image_url' => (string) $this->getProductImageUrl($quoteItem->getStoreId(), $baseService),
                'qty_ordered' => $quoteItem->getQty() ? (float) $quoteItem->getQty() :
                    ($quoteItem->getQtyOrdered() ? (float) $quoteItem->getQtyOrdered() : 1),
                'price_amount' => (float) round($quoteItem->getPrice(), 2),
                'row_total_amount' => (float) round($quoteItem->getRowTotal(), 2),
                'tax_amount' => (float) round($quoteItem->getTaxAmount(), 2),
                'discount_amount' => (float) round($quoteItem->getTotalDiscountAmount(), 2),
                'product_options' => $this->getProductOptions($baseService)
            ];
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getProductOptions(BaseService $baseService): array
    {
        $sortedOptions = [];
        try {
            if (! $this->product instanceof Product) {
                return $sortedOptions;
            }

            $options = $this->product->getTypeInstance()->getOrderOptions($this->product);

            if (isset($options['attributes_info']) || isset($options['options'])) {
                $optionAttributes = [];

                if (isset($options['attributes_info'])) {
                    $optionAttributes = $options['attributes_info'];
                } elseif (isset($options['options'])) {
                    $optionAttributes = $options['options'];
                }

                $sortedOptions = $this->getConfigurableOptions($optionAttributes, $baseService);
            } elseif (isset($options['bundle_options'])) {
                $sortedOptions = $this->getBundleOptions($options, $baseService);
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return $sortedOptions;
    }

    /**
     * @param array $optionAttributes
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getConfigurableOptions(array $optionAttributes, BaseService $baseService): array
    {
        $sortedOptions = [];
        try {
            foreach ($optionAttributes as $attribute) {
                $option['option_label'] = (string) $attribute['label'];
                $values['title'] = (string) $attribute['label'];
                $values['value'] = (string) $attribute['value'];
                $values['qty'] = 1;
                $values['price'] = 0;
                $option['option_value'] = $values;
                $sortedOptions[] = $option;
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return $sortedOptions;
    }

    /**
     * @param array $options
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getBundleOptions(array $options, BaseService $baseService): array
    {
        $sortedOptions = [];
        try {
            foreach ($options['bundle_options'] as $attribute) {
                $option['option_label'] = (string) $attribute['label'];

                foreach ($attribute['value'] as $value) {
                    $values['title'] = (string) $value['title'];
                    $values['value'] = '';
                    $values['qty'] = round($value['qty'], 2);
                    $values['price'] = round($value['price'], 2);
                    $option['option_value'] = $values;
                }

                $sortedOptions[] = $option;
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }

        return $sortedOptions;
    }
}
