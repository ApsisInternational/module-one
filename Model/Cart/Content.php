<?php

namespace Apsis\One\Model\Cart;

use Apsis\One\Model\Events\Historical\EventData;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\App\EmulationFactory;
use Throwable;

class Content extends EventData
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
     * @var ApsisDateHelper
     */
    private ApsisDateHelper $apsisDateHelper;

    /**
     * Content constructor.
     *
     * @param EmulationFactory $emulationFactory
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param ApsisDateHelper $apsisDateHelper
     * @param ProductServiceProvider $productServiceProvider
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        EmulationFactory $emulationFactory,
        CartTotalRepositoryInterface $cartTotalRepository,
        ApsisDateHelper $apsisDateHelper,
        ProductServiceProvider $productServiceProvider,
        ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($productServiceProvider, $productRepository);
        $this->productServiceProvider = $productServiceProvider;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->emulationFactory = $emulationFactory;
    }

    /**
     * @param Quote $quoteModel
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getCartData(Quote $quoteModel, ApsisCoreHelper $apsisCoreHelper): array
    {
        $cartData = [];
        $this->apsisCoreHelper = $apsisCoreHelper;
        $appEmulation = $this->emulationFactory->create();

        try {
            $appEmulation->startEnvironmentEmulation($quoteModel->getStoreId(), Area::AREA_FRONTEND, true);
            $cartData = $this->getProcessedDataArr($quoteModel);
            $appEmulation->stopEnvironmentEmulation();
        } catch (Throwable $e) {
            $appEmulation->stopEnvironmentEmulation();
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $cartData;
    }

    /**
     * @inheritdoc
     */
    protected function getProcessedDataArr(AbstractModel $model): array
    {
        $cartData = [];

        try {
            /** @var Quote $model */
            $cartData = $this->getMainCartData($model);
            $cartData['items'] = $this->getItemData($model->getAllVisibleItems());
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $cartData;
    }

    /**
     * @param array $quoteItems
     *
     * @return array
     */
    private function getItemData(array $quoteItems): array
    {
        try {
            $itemsData = [];

            /** @var Item $quoteItem */
            foreach ($quoteItems as $quoteItem) {
                $itemsData[] = $this->getItemsData($quoteItem);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $itemsData;
    }

    /**
     * @param Quote $quoteModel
     *
     * @return array
     */
    private function getMainCartData(Quote $quoteModel): array
    {
        try {
            $totals = $this->cartTotalRepository->get($quoteModel->getId());
            return [
                'cart_id' => (int) $quoteModel->getId(),
                'created_at' => (int) $this->apsisDateHelper
                    ->formatDateForPlatformCompatibility($quoteModel->getCreatedAt()),
                'updated_at' => (int) $this->apsisDateHelper
                    ->formatDateForPlatformCompatibility($quoteModel->getUpdatedAt()),
                'store_name' => (string) $quoteModel->getStore()->getName(),
                'website_name' => (string) $quoteModel->getStore()->getWebsite()->getName(),
                'subtotal_amount' => (float) $this->apsisCoreHelper->round($totals->getSubtotal()),
                'grand_total_amount' => (float) $this->apsisCoreHelper->round($quoteModel->getGrandTotal()),
                'tax_amount' => (float) $this->apsisCoreHelper->round($totals->getTaxAmount()),
                'shipping_amount' => (float) $this->apsisCoreHelper->round($totals->getShippingAmount()),
                'discount_amount' => (float) $this->apsisCoreHelper->round($totals->getDiscountAmount()),
                'items_quantity' => (float) $this->apsisCoreHelper->round($totals->getItemsQty()),
                'items_count' => (float) $this->apsisCoreHelper->round($quoteModel->getItemsCount()),
                'payment_method_title' => (string) $quoteModel->getPayment()->getMethod(),
                'shipping_method_title' => (string) $quoteModel->getShippingAddress()->getShippingDescription(),
                'currency_code' => (string) $totals->getQuoteCurrencyCode(),
                'customer_info' => $this->getCustomerInformation($quoteModel),
                'shipping_billing_same' => (boolean) $quoteModel->getShippingAddress()->getSameAsBilling(),
                'shipping_address' => $this->getAddress($quoteModel->getShippingAddress()),
                'billing_address' => $this->getAddress($quoteModel->getBillingAddress()),
            ];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Address $address
     *
     * @return array
     */
    private function getAddress(Address $address): array
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
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Quote $quoteModel
     *
     * @return array
     */
    private function getCustomerInformation(Quote $quoteModel): array
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
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Item $quoteItem
     *
     * @return array
     */
    private function getItemsData(Item $quoteItem): array
    {
        try {
            if ($quoteItem->getProductId()) {
                $product = $this->loadProduct($quoteItem->getProductId(), $quoteItem->getStoreId());
            }

            if (isset($product) && $product instanceof Product) {
                $this->fetchProduct($product);
            } else {
                $this->fetchProduct($quoteItem);
            }

            return [
                'product_id' => (int) $quoteItem->getProductId(),
                'sku' => (string) $quoteItem->getSku(),
                'name' => (string) $quoteItem->getName(),
                'product_url' => (string) $this->getProductUrl($quoteItem->getStoreId()),
                'product_image_url' => (string) $this->getProductImageUrl($quoteItem->getStoreId()),
                'qty_ordered' => $quoteItem->getQty() ? (float) $quoteItem->getQty() :
                    ($quoteItem->getQtyOrdered() ? (float) $quoteItem->getQtyOrdered() : 1),
                'price_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getPrice()),
                'row_total_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getRowTotal()),
                'tax_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getTaxAmount()),
                'discount_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getTotalDiscountAmount()),
                'product_options' => $this->getProductOptions()
            ];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @return array
     */
    private function getProductOptions(): array
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

                $sortedOptions = $this->getConfigurableOptions($optionAttributes);
            } elseif (isset($options['bundle_options'])) {
                $sortedOptions = $this->getBundleOptions($options);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $sortedOptions;
    }

    /**
     * @param array $optionAttributes
     *
     * @return array
     */
    private function getConfigurableOptions(array $optionAttributes): array
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
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $sortedOptions;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function getBundleOptions(array $options): array
    {
        $sortedOptions = [];
        try {
            foreach ($options['bundle_options'] as $attribute) {
                $option['option_label'] = (string) $attribute['label'];

                foreach ($attribute['value'] as $value) {
                    $values['title'] = (string) $value['title'];
                    $values['value'] = '';
                    $values['qty'] = $this->apsisCoreHelper->round($value['qty']);
                    $values['price'] = $this->apsisCoreHelper->round($value['price']);
                    $option['option_value'] = $values;
                }

                $sortedOptions[] = $option;
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $sortedOptions;
    }
}
