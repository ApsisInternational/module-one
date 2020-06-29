<?php

namespace Apsis\One\Model\Cart;

use Apsis\One\Model\Service\Product as ProductServiceProvider;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Store\Model\App\Emulation;
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
     * @var Data
     */
    private $priceHelper;

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
     * @param Data $priceHelper
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param ApsisDateHelper $apsisDateHelper
     * @param ProductServiceProvider $productServiceProvider
     */
    public function __construct(
        EmulationFactory $emulationFactory,
        Data $priceHelper,
        CartTotalRepositoryInterface $cartTotalRepository,
        ApsisDateHelper $apsisDateHelper,
        ProductServiceProvider $productServiceProvider
    ) {
        $this->productServiceProvider = $productServiceProvider;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->priceHelper = $priceHelper;
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
        $this->apsisCoreHelper = $apsisCoreHelper;
        /** @var Emulation $appEmulation */
        $appEmulation = $this->emulationFactory->create();

        try {
            $appEmulation->startEnvironmentEmulation($quoteModel->getStoreId(), Area::AREA_FRONTEND, true);
            $cartData = (array) $this->getMainCartData($quoteModel);
            $cartData['items'] = (array) $this->getItemData($quoteModel->getAllVisibleItems());
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            $appEmulation->stopEnvironmentEmulation();
            return [];
        }

        $appEmulation->stopEnvironmentEmulation();
        return (array) $cartData;
    }

    /**
     * @param array $quoteItems
     *
     * @return array
     */
    private function getItemData(array $quoteItems)
    {
        $itemsData = [];

        /** @var Item $quoteItem */
        foreach ($quoteItems as $quoteItem) {
            $itemsData[] = $this->getItemsData($quoteItem);
        }

        return $itemsData;
    }

    /**
     * @param Quote $quoteModel
     * @return mixed
     *
     * @throws NoSuchEntityException
     */
    private function getMainCartData(Quote $quoteModel)
    {
        $totals = $this->cartTotalRepository->get($quoteModel->getId());
        $quoteData['cart_id'] = (int) $quoteModel->getId();
        $quoteData['created_at'] = (int) $this->apsisDateHelper
            ->formatDateForPlatformCompatibility($quoteModel->getCreatedAt());
        $quoteData['updated_at'] = (int) $this->apsisDateHelper
            ->formatDateForPlatformCompatibility($quoteModel->getUpdatedAt());
        $quoteData['store_name'] = (string) $quoteModel->getStore()->getName();
        $quoteData['website_name'] = (string) $quoteModel->getStore()->getWebsite()->getName();
        $quoteData['subtotal_amount'] = (float) $this->apsisCoreHelper->round($totals->getSubtotal());
        $quoteData['grand_total_amount'] = (float) $this->apsisCoreHelper->round($quoteModel->getGrandTotal());
        $quoteData['tax_amount'] = (float) $this->apsisCoreHelper->round($totals->getTaxAmount());
        $quoteData['shipping_amount'] = (float) $this->apsisCoreHelper->round($totals->getShippingAmount());
        $quoteData['discount_amount'] = (float) $this->apsisCoreHelper->round($totals->getDiscountAmount());
        $quoteData['items_quantity'] = (float) $this->apsisCoreHelper->round($totals->getItemsQty());
        $quoteData['items_count'] = (float) $this->apsisCoreHelper->round($quoteModel->getItemsCount());
        $quoteData['payment_method_title'] = (string) $quoteModel->getPayment()->getMethod();
        $quoteData['shipping_method_title'] = (string) $quoteModel->getShippingAddress()->getShippingDescription();
        $quoteData['currency_code'] = (string) $totals->getQuoteCurrencyCode();
        $quoteData['customer_info'] = (array) $this->getCustomerInformation($quoteModel);
        $quoteData['shipping_billing_same'] = (boolean) $quoteModel->getShippingAddress()->getSameAsBilling();
        $quoteData['shipping_address'] = (array) $this->getAddress($quoteModel->getShippingAddress());
        $quoteData['billing_address'] = (array) $this->getAddress($quoteModel->getBillingAddress());
        return $quoteData;
    }

    /**
     * @param Address $address
     *
     * @return array
     */
    private function getAddress(Address $address)
    {
        $addressInfo['prefix'] = (string) $address->getPrefix();
        $addressInfo['suffix'] = (string) $address->getSuffix();
        $addressInfo['first_name'] = (string) $address->getFirstname();
        $addressInfo['middle_name'] = (string) $address->getMiddlename();
        $addressInfo['last_name'] = (string) $address->getLastname();
        $addressInfo['company'] = (string) $address->getCompany();
        $addressInfo['street_line_1'] = (string) $address->getStreetLine(1);
        $addressInfo['street_line_2'] = (string) $address->getStreetLine(2);
        $addressInfo['city'] = (string) $address->getCity();
        $addressInfo['region'] = (string) $address->getRegion();
        $addressInfo['postcode'] = (string) $address->getPostcode();
        $addressInfo['country'] = (string) $address->getCountry();
        $addressInfo['telephone'] = (string) $address->getTelephone();
        return $addressInfo;
    }

    /**
     * @param Quote $quoteModel
     *
     * @return array
     */
    private function getCustomerInformation(Quote $quoteModel)
    {
        $customer['customer_id'] = (int) $quoteModel->getCustomerId();
        $customer['is_guest'] = (boolean) $quoteModel->getCustomerIsGuest();
        $customer['email'] = (string) $quoteModel->getCustomerEmail();
        $customer['prefix'] = (string) $quoteModel->getCustomerPrefix();
        $customer['suffix'] = (string) $quoteModel->getCustomerSuffix();
        $customer['first_name'] = (string) $quoteModel->getCustomerFirstname();
        $customer['middle_name'] = (string) $quoteModel->getCustomerMiddlename();
        $customer['last_name'] = (string) $quoteModel->getCustomerLastname();
        return $customer;
    }

    /**
     * @param Item $quoteItem
     *
     * @return array
     */
    private function getItemsData(Item $quoteItem)
    {
        $product = $quoteItem->getProduct();
        $itemsData = [
            'product_id' => (int) $quoteItem->getProductId(),
            'sku' => (string) $quoteItem->getSku(),
            'name' => (string) $quoteItem->getName(),
            'product_url' => (string) $product->getProductUrl(),
            'product_image_url' => (string) $this->productServiceProvider->getProductImageUrl($product),
            'qty_ordered' => (float) $quoteItem->getQty() ? $quoteItem->getQty() :
                ($quoteItem->getQtyOrdered() ? $quoteItem->getQtyOrdered() : 1),
            'price_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getPrice()),
            'row_total_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getRowTotal()),
            'tax_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getTaxAmount()),
            'discount_amount' => (float) $this->apsisCoreHelper->round($quoteItem->getTotalDiscountAmount()),
            'product_options' => $this->getProductOptions($quoteItem)
        ];

        return $itemsData;
    }

    /**
     * @param Item $item
     *
     * @return array
     */
    private function getProductOptions(Item $item)
    {
        $options = $item->getProduct()->getTypeInstance()->getOrderOptions($item->getProduct());
        $sortedOptions = [];
        if (isset($options['attributes_info']) || isset($options['options'])) {
            $optionAttributes = [];
            if (isset($options['attributes_info'])) {
                $optionAttributes = $options['attributes_info'];
            } elseif (isset($options['options'])) {
                $optionAttributes = $options['options'];
            }
            $sortedOptions = $this->getConfigurableOptions($optionAttributes, $sortedOptions);
        } elseif (isset($options['bundle_options'])) {
            $sortedOptions = $this->getBundleOptions($options, $sortedOptions);
        }

        return $sortedOptions;
    }

    /**
     * @param array $optionAttributes
     * @param array $sortedOptions
     *
     * @return array
     */
    private function getConfigurableOptions(array $optionAttributes, array $sortedOptions)
    {
        foreach ($optionAttributes as $attribute) {
            $option['option_label'] = (string) $attribute['label'];
            $values['title'] = (string) $attribute['label'];
            $values['value'] = (string) $attribute['value'];
            $values['qty'] = 1;
            $values['price'] = 0;
            $option['option_value'] = $values;
            $sortedOptions[] = $option;
        }
        return $sortedOptions;
    }

    /**
     * @param array $options
     * @param array $sortedOptions
     *
     * @return array
     */
    private function getBundleOptions(array $options, array $sortedOptions)
    {
        foreach ($options['bundle_options'] as $attribute) {
            $option['option_label'] = (string) $attribute['label'];
            foreach ($attribute['value'] as $value) {
                $values['title'] = (string) $value['title'];
                $values['value'] = '';
                $values['qty'] = (float) $this->apsisCoreHelper->round($value['qty']);
                $values['price'] = (float) $this->apsisCoreHelper->round($value['price']);
                $option['option_value'] = $values;
            }
            $sortedOptions[] = $option;
        }
        return $sortedOptions;
    }
}