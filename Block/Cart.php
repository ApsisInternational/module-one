<?php

namespace Apsis\One\Block;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Apsis\One\Model\Service\Log as ApsisLogHelper;

/**
 * Cart block
 *
 * @api
 */
class Cart extends Template
{
    const APSIS_CART_HANDLE_ENDPOINT = 'apsis/abandoned/checkout';

    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

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
     * @param ApsisLogHelper $apsisLogHelper
     * @param PriceHelper $priceHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ApsisLogHelper $apsisLogHelper,
        PriceHelper $priceHelper,
        array $data = []
    ) {
        $this->apsisLogHelper = $apsisLogHelper;
        $this->priceHelper = $priceHelper;
        parent::__construct($context, $data);
    }

    /**
     * @param DataObject $cart
     *
     * @return $this
     */
    public function setCart(DataObject $cart)
    {
        $this->cart = $cart;
        return $this;
    }

    /**
     * @return array
     */
    public function getCartItems()
    {
        try {
            $obj = $this->apsisLogHelper->unserialize($this->cart->getCartData());
            if (isset($obj->items) && is_array($obj->items)) {
                return $this->getItemsWithLimitApplied($obj->items);
            }
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
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
        try {
            $limit = (int) $this->getRequest()->getParam('limit');
            if (empty($limit)) {
                return $items;
            }

            if (count($items) > $limit) {
                return array_splice($items, 0, $limit);
            }
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return $items;
    }

    /**
     * @param float $value
     *
     * @return float|string
     */
    public function getCurrencyByStore(float $value)
    {
        try {
            $storeId = $this->cart->getStoreId();
            return $this->priceHelper->currencyByStore($value, $storeId, true, false);
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return $value;
    }

    /**
     * @return string
     */
    public function getUrlForCheckoutLink()
    {
        $params = ['token' => $this->cart->getToken()];
        try {
            $storeId = $this->cart->getStoreId();
            return $this->_storeManager->getStore($storeId)->getUrl(self::APSIS_CART_HANDLE_ENDPOINT, $params);
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->getUrl(self::APSIS_CART_HANDLE_ENDPOINT, $params);
        }
    }
}
