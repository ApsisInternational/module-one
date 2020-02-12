<?php

namespace Apsis\One\Block;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Apsis\One\Helper\Core as ApsisCoreHelper;

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
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Cart constructor.
     *
     * @param Template\Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Registry $registry
     * @param PriceHelper $priceHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        Registry $registry,
        PriceHelper $priceHelper,
        array $data = []
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
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
            $obj = $this->apsisCoreHelper->unserialize($this->cart->getCartData());
            if (isset($obj->items) && is_array($obj->items)) {
                return $this->getItemsWithLimitApplied($obj->items);
            }
        }
        return [];
    }

    /**
     * @param array $items
     *
     * @return array
     */
    private function getItemsWithLimitApplied(array $items)
    {
        $limit = $this->getRequest()->getParam('limit');
        if (! $limit) {
            return $items;
        }

        $limit = (int) $limit;
        if (count($items) > $limit) {
            return array_splice($items, 0, $limit);
        }
        return $items;
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
     */
    public function getUrlForCheckoutLink()
    {
        try {
            $storeId = $this->cart->getStoreId();
            return $this->_storeManager->getStore($storeId)->getUrl(
                'apsis/abandoned/checkout',
                ['quote_id' => $this->cart->getQuoteId()]
            );
        } catch (Exception $e) {
            return $this->getUrl(
                'apsis/abandoned/checkout',
                ['quote_id' => $this->cart->getQuoteId()]
            );
        }
    }
}
