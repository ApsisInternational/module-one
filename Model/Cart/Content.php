<?php

namespace Apsis\One\Model\Cart;

use Magento\Catalog\Block\Product\Image;
use Magento\Catalog\Block\Product\ImageBuilderFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Cart\CartTotalRepository;
use Magento\Quote\Model\Quote\Address;

class Content
{
    /**
     * @var ImageBuilderFactory
     */
    private $imageBuilderFactory;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var Data
     */
    private $priceHelper;

    /**
     * @var EmulationFactory
     */
    private $emulationFactory;

    /**
     * @var CartTotalRepository
     */
    private $cartTotalRepository;

    /**
     * Content constructor.
     *
     * @param EmulationFactory $emulationFactory
     * @param QuoteFactory $quoteFactory
     * @param Data $priceHelper
     * @param ImageBuilderFactory $imageBuilderFactory
     * @param CartTotalRepository $cartTotalRepository
     */
    public function __construct(
        EmulationFactory $emulationFactory,
        QuoteFactory $quoteFactory,
        Data $priceHelper,
        ImageBuilderFactory $imageBuilderFactory,
        CartTotalRepository $cartTotalRepository
    ) {
        $this->cartTotalRepository = $cartTotalRepository;
        $this->quoteFactory = $quoteFactory;
        $this->priceHelper = $priceHelper;
        $this->emulationFactory = $emulationFactory;
        $this->imageBuilderFactory = $imageBuilderFactory;
    }

    /**
     * @param string|int $quoteId
     *
     * @return array|bool
     */
    public function getCartData($quoteId)
    {
        $quoteId = (int) $quoteId;
        $quoteModel = $this->quoteFactory->create()
            ->loadActive($quoteId);
        $quoteItems = $quoteModel->getAllVisibleItems();

        if (! $quoteModel->getId() || empty($quoteItems)) {
            return false;
        }

        /** @var Emulation $appEmulation */
        $appEmulation = $this->emulationFactory->create();

        try {
            $appEmulation->startEnvironmentEmulation($quoteModel->getStoreId(), Area::AREA_FRONTEND, true);
            $cartData = $this->getMainCartData($quoteModel);
            $cartData['items'] = $this->getItemData($quoteItems);
        } catch (\Exception $e) {
            $appEmulation->stopEnvironmentEmulation();
            return false;
        }

        $appEmulation->stopEnvironmentEmulation();
        return $cartData;
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
        $quoteData['created_at'] = (string) $quoteModel->getCreatedAt();
        $quoteData['updated_at'] = (string) $quoteModel->getUpdatedAt();
        $quoteData['subtotal_amount'] = (float) $this->round($totals->getSubtotal());
        $quoteData['grand_total_amount'] = (float) $this->round($quoteModel->getGrandTotal());
        $quoteData['tax_amount'] = (float) $this->round($totals->getTaxAmount());
        $quoteData['shipping_amount'] = (float) $this->round($totals->getShippingAmount());
        $quoteData['discount_amount'] = (float) $this->round($totals->getDiscountAmount());
        $quoteData['items_quantity'] = (float) $this->round($totals->getItemsQty());
        $quoteData['items_count'] = (float) $this->round($quoteModel->getItemsCount());
        $quoteData['payment_method_title'] = (string) $quoteModel->getPayment()->getMethod();
        $quoteData['shipping_method_title'] = (string) $quoteModel->getShippingAddress()->getShippingDescription();
        $quoteData['currency_code'] = (string) $totals->getQuoteCurrencyCode();
        $quoteData['customer_info'] = $this->getCustomerInformation($quoteModel);
        $quoteData['shipping_billing_same'] = (boolean) $quoteModel->getShippingAddress()->getSameAsBilling();
        $quoteData['shipping_address'] = $this->getAddress($quoteModel->getShippingAddress());
        $quoteData['billing_address'] = $this->getAddress($quoteModel->getBillingAddress());
        return $quoteData;
    }

    /**
     * @param Address $address
     *
     * @return array
     */
    private function getAddress(Address $address)
    {
        $addressInfo['email'] = (string) $address->getEmail();
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
            'product_image_url' => (string) $this->getProductImageUrl($product),
            'qty_ordered' => (float) $quoteItem->getQty() ? $quoteItem->getQty() :
                ($quoteItem->getQtyOrdered() ? $quoteItem->getQtyOrdered() : 1),
            'price_amount' => (float) $this->round($quoteItem->getPrice()),
            'row_total_amount' => (float) $this->round($quoteItem->getRowTotal()),
            'tax_amount' => (float) $this->round($quoteItem->getTaxAmount()),
            'discount_amount' => (float) $this->round($quoteItem->getTotalDiscountAmount()),
            'product_options' => $this->getProductOptions($quoteItem)
        ];

        return $itemsData;
    }

    /**
     * @param Item $item
     *
     * @return array
     */
    public function getProductOptions(Item $item)
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
     * @param float $price
     * @param int $precision
     *
     * @return float
     */
    private function round($price, $precision = 2)
    {
        return (float) round($price, $precision);
    }

    /**
     * @param Product $product
     * @param string $imageId
     *
     * @return string
     */
    public function getProductImageUrl(Product $product, string $imageId = 'cart_page_product_thumbnail')
    {
        /** @var Image $image */
        $image = $this->imageBuilderFactory
            ->create()
            ->setProduct($product)
            ->setImageId($imageId)
            ->create();

        return $image->getImageUrl();
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
                $values['qty'] = (float) $this->round($value['qty']);
                $values['price'] = (float) $this->round($value['price']);
                $option['option_value'] = $values;
            }
            $sortedOptions[] = $option;
        }
        return $sortedOptions;
    }
}
