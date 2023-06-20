<?php

namespace Apsis\One\Service\Data\Cart;

use Apsis\One\Service\Data\AbstractData;
use Apsis\One\Service\BaseService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
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
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Image $imageHelper
     * @param EmulationFactory $emulationFactory
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param BaseService $baseService
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Image $imageHelper,
        EmulationFactory $emulationFactory,
        CartTotalRepositoryInterface $cartTotalRepository,
        BaseService $baseService
    ) {
        parent::__construct($productRepository, $imageHelper);
        $this->baseService = $baseService;
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
        $cartData = [];
        $appEmulation = $this->getEmulationModel();

        try {
            $appEmulation->startEnvironmentEmulation($quoteModel->getStoreId(), Area::AREA_FRONTEND, true);
            if (! empty($cartData = $this->getMainCartData($quoteModel, $baseService))) {
                $cartData['items'] = $this->getItemData($quoteModel->getAllVisibleItems(), $baseService);
            }
            $appEmulation->stopEnvironmentEmulation();
        } catch (Throwable $e) {
            $appEmulation->stopEnvironmentEmulation();
            $baseService->logError(__METHOD__, $e);
        }

        return $cartData;
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
                'created_at' => (int) $this->baseService
                    ->formatDateForPlatformCompatibility($quoteModel->getCreatedAt()),
                'updated_at' => (int) $this->baseService
                    ->formatDateForPlatformCompatibility($quoteModel->getUpdatedAt()),
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
     * @param Item $quoteItem
     * @param BaseService $baseService
     *
     * @return array
     */
    private function getItemsData(Item $quoteItem, BaseService $baseService): array
    {
        try {
            if ($quoteItem->getProductId()) {
                $product = $this->loadProduct($quoteItem->getProductId(), $quoteItem->getStoreId(), $baseService);
            }

            if (isset($product) && $product instanceof Product) {
                $this->fetchProduct($product, $baseService);
            } else {
                $this->fetchProduct($quoteItem, $baseService);
            }

            return [
                'product_id' => (int) $quoteItem->getProductId(),
                'sku' => (string) $quoteItem->getSku(),
                'name' => (string) $quoteItem->getName(),
                'product_url' => (string) $this->getProductUrl($quoteItem->getStoreId(), $baseService),
                'product_image_url' => (string) $this->getProductImageUrl($quoteItem->getStoreId(), $baseService),
                'qty_ordered' => $quoteItem->getQty() ? (float) $quoteItem->getQty() :
                    ($quoteItem->getQtyOrdered() ? (float) $quoteItem->getQtyOrdered() : 1),
                'price_amount' => (float) round($quoteItem->getPrice()),
                'row_total_amount' => (float) round($quoteItem->getRowTotal()),
                'tax_amount' => (float) round($quoteItem->getTaxAmount()),
                'discount_amount' => (float) round($quoteItem->getTotalDiscountAmount()),
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
                    $values['qty'] = round($value['qty']);
                    $values['price'] = round($value['price']);
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
