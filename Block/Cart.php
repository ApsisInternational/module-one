<?php

namespace Apsis\One\Block;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

/**
 * Cart block
 *
 * @api
 */
class Cart extends Template
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    /**
     * @var DataObject
     */
    private $cart;

    /**
     * Cart constructor.
     *
     * @param Template\Context $context
     * @param Registry $registry
     * @param PriceHelper $priceHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Registry $registry,
        PriceHelper $priceHelper,
        array $data = []
    ) {
        $this->priceHelper = $priceHelper;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * @return array
     */
    public function getCartItems()
    {
        $cart = $this->registry->registry('apsis_one_cart');
        if ($cart instanceof DataObject) {
            $this->cart = $cart;
            $obj = json_decode($this->cart->getCartData());
            if (isset($obj->items)) {
                return $obj->items;
            }
        }
        return [];
    }

    /**
     * @param float $value
     *
     * @return float|string
     */
    public function getCurrencyByStore($value)
    {
        $storeId = $this->cart->getStoreId();
        return $this->priceHelper->currencyByStore($value, $storeId, true, false);
    }

    /**
     * @return string
     *
     * @throws NoSuchEntityException
     */
    public function getUrlForCheckoutLink()
    {
        $storeId = $this->cart->getStoreId();
        return $this->_storeManager->getStore($storeId)->getUrl(
            'apsis/abandoned/checkout',
            ['quote_id' => $this->cart->getQuoteId()]
        );
    }
}
