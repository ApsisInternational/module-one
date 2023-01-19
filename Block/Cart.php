<?php

namespace Apsis\One\Block;

use Apsis\One\Model\Abandoned;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Throwable;

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
    private ApsisLogHelper $apsisLogHelper;

    /**
     * @var PriceHelper
     */
    private PriceHelper $priceHelper;

    /**
     * @var DataObject|Abandoned
     */
    private DataObject|Abandoned $cart;

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
     * @param DataObject|Abandoned $cart
     *
     * @return $this
     */
    public function setCart(DataObject|Abandoned $cart): static
    {
        $this->cart = $cart;
        return $this;
    }

    /**
     * @return array
     */
    public function getCartItems(): array
    {
        try {
            $obj = $this->apsisLogHelper->unserialize($this->cart->getCartData());
            if (isset($obj->items) && is_array($obj->items)) {
                return $this->getItemsWithLimitApplied($obj->items);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return [];
    }

    /**
     * @param array $items
     *
     * @return array
     */
    private function getItemsWithLimitApplied(array $items): array
    {
        try {
            $limit = (int) $this->getRequest()->getParam('limit');
            if (empty($limit)) {
                return $items;
            }

            if (count($items) > $limit) {
                return array_splice($items, 0, $limit);
            }
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return $items;
    }

    /**
     * @param float $value
     *
     * @return float|string
     */
    public function getCurrencyByStore(float $value): float|string
    {
        try {
            $storeId = $this->cart->getStoreId();
            return $this->priceHelper->currencyByStore($value, $storeId, true, false);
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
        }
        return $value;
    }

    /**
     * @return string
     */
    public function getUrlForCheckoutLink(): string
    {
        $params = ['token' => $this->cart->getToken()];
        try {
            $storeId = $this->cart->getStoreId();
            return $this->_storeManager->getStore($storeId)->getUrl(self::APSIS_CART_HANDLE_ENDPOINT, $params);
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return $this->getUrl(self::APSIS_CART_HANDLE_ENDPOINT, $params);
        }
    }
}
