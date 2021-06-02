<?php

namespace Apsis\One\Model\Cart;

use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Exception;
use Magento\Framework\App\Area;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\Quote\Address;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Date as ApsisDateHelper;

class Content
{
    /**
     * @var ProductServiceProvider
     */
    private $productServiceProvider;

    /**
     * @var EmulationFactory
     */
    private $emulationFactory;

    /**
     * @var CartTotalRepositoryInterface
     */
    private $cartTotalRepository;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ApsisDateHelper
     */
    private $apsisDateHelper;

    /**
     * Content constructor.
     *
     * @param EmulationFactory $emulationFactory
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param ApsisDateHelper $apsisDateHelper
     * @param ProductServiceProvider $productServiceProvider
     */
    public function __construct(
        EmulationFactory $emulationFactory,
        CartTotalRepositoryInterface $cartTotalRepository,
        ApsisDateHelper $apsisDateHelper,
        ProductServiceProvider $productServiceProvider
    ) {
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
    public function getCartData(Quote $quoteModel, ApsisCoreHelper $apsisCoreHelper)
    {
        $cartData = [];
        $this->apsisCoreHelper = $apsisCoreHelper;
        $appEmulation = $this->emulationFactory->create();

        try {
            $appEmulation->startEnvironmentEmulation($quoteModel->getStoreId(), Area::AREA_FRONTEND, true);
            $cartData = $this->getMainCartData($quoteModel);
            $cartData['items'] = $this->getItemData($quoteModel->getAllVisibleItems());
        } catch (Exception $e) {
            $appEmulation->stopEnvironmentEmulation();
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $cartData;
    }

    /**
     * @param array $quoteItems
     *
     * @return array
     */
    private function getItemData(array $quoteItems)
    {
        try {
            $itemsData = [];

            /** @var Item $quoteItem */
            foreach ($quoteItems as $quoteItem) {
                $itemsData[] = $this->getItemsData($quoteItem);
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $itemsData;
    }

    /**
     * @param Quote $quoteModel
     *
     * @return array
     */
    private function getMainCartData(Quote $quoteModel)
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
                'subtotal_amount' => $this->apsisCoreHelper->round($totals->getSubtotal()),
                'grand_total_amount' => $this->apsisCoreHelper->round($quoteModel->getGrandTotal()),
                'tax_amount' => $this->apsisCoreHelper->round($totals->getTaxAmount()),
                'shipping_amount' => $this->apsisCoreHelper->round($totals->getShippingAmount()),
                'discount_amount' => $this->apsisCoreHelper->round($totals->getDiscountAmount()),
                'items_quantity' => $this->apsisCoreHelper->round($totals->getItemsQty()),
                'items_count' => $this->apsisCoreHelper->round($quoteModel->getItemsCount()),
                'payment_method_title' => (string) $quoteModel->getPayment()->getMethod(),
                'shipping_method_title' => (string) $quoteModel->getShippingAddress()->getShippingDescription(),
                'currency_code' => (string) $totals->getQuoteCurrencyCode(),
                'customer_info' => $this->getCustomerInformation($quoteModel),
                'shipping_billing_same' => (boolean) $quoteModel->getShippingAddress()->getSameAsBilling(),
                'shipping_address' => $this->getAddress($quoteModel->getShippingAddress()),
                'billing_address' => $this->getAddress($quoteModel->getBillingAddress()),
            ];
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Address $address
     *
     * @return array
     */
    private function getAddress(Address $address)
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Quote $quoteModel
     *
     * @return array
     */
    private function getCustomerInformation(Quote $quoteModel)
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param Item $quoteItem
     *
     * @return array
     */
    private function getItemsData(Item $quoteItem)
    {
        $product = $quoteItem->getProduct();
        return [
            'product_id' => (int) $quoteItem->getProductId(),
            'sku' => (string) $quoteItem->getSku(),
            'name' => (string) $quoteItem->getName(),
            'product_url' => (string) $product->getProductUrl(),
            'product_image_url' => (string) $this->productServiceProvider->getProductImageUrl($product),
            'qty_ordered' => (float) $quoteItem->getQty() ? $quoteItem->getQty() :
                ($quoteItem->getQtyOrdered() ? $quoteItem->getQtyOrdered() : 1),
            'price_amount' => $this->apsisCoreHelper->round($quoteItem->getPrice()),
            'row_total_amount' => $this->apsisCoreHelper->round($quoteItem->getRowTotal()),
            'tax_amount' => $this->apsisCoreHelper->round($quoteItem->getTaxAmount()),
            'discount_amount' => $this->apsisCoreHelper->round($quoteItem->getTotalDiscountAmount()),
            'product_options' => $this->getProductOptions($quoteItem)
        ];
    }

    /**
     * @param Item $item
     *
     * @return array
     */
    private function getProductOptions(Item $item)
    {
        $sortedOptions = [];
        try {
            $options = $item->getProduct()->getTypeInstance()->getOrderOptions($item->getProduct());

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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $sortedOptions;
    }

    /**
     * @param array $optionAttributes
     *
     * @return array
     */
    private function getConfigurableOptions(array $optionAttributes)
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return $sortedOptions;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function getBundleOptions(array $options)
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $sortedOptions;
    }
}
