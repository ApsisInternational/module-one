<?php

namespace Apsis\One\Block\Abandoned;

use Apsis\One\Model\AbandonedModel;
use Apsis\One\Service\BaseService;
use Magento\Framework\Escaper;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Throwable;

class CartBlock extends Template
{
    const CHECKOUT_ENDPOINT = 'apsis/abandoned/checkout';

    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var Data
     */
    private Data $priceHelper;

    /**
     * @var AbandonedModel
     */
    private AbandonedModel $abandonedModel;

    /**
     * @var Escaper
     */
    public Escaper $escaper;

    /**
     * @param Context $context
     * @param BaseService $baseService
     * @param Data $priceHelper
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(
        Context $context,
        BaseService $baseService,
        Data $priceHelper,
        Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->baseService = $baseService;
        $this->priceHelper = $priceHelper;
        $this->escaper = $escaper;
    }

    /**
     * @param AbandonedModel $abandonedModel
     *
     * @return $this
     */
    public function setAbandonedModel(AbandonedModel $abandonedModel): static
    {
        $this->abandonedModel = $abandonedModel;
        return $this;
    }

    /**
     * @return array
     */
    public function getCartItems(): array
    {
        try {
            $obj = json_decode($this->abandonedModel->getCartData());
            if (isset($obj->items) && is_array($obj->items)) {
                return $this->getItemsWithLimitApplied($obj->items);
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
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
            $this->baseService->logError(__METHOD__, $e);
        }
        return $items;
    }

    /**
     * @param float $value
     *
     * @return string
     */
    public function getCurrencyByStore(float $value): string
    {
        try {
            $storeId = $this->abandonedModel->getStoreId();
            return (string) $this->priceHelper->currencyByStore($value, $storeId, true, false);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
        return $value;
    }

    /**
     * @return string
     */
    public function getUrlForCheckoutLink(): string
    {
        $params = ['token' => $this->abandonedModel->getToken()];
        try {
            $storeId = $this->abandonedModel->getStoreId();
            return $this->_storeManager->getStore($storeId)->getUrl(self::CHECKOUT_ENDPOINT, $params);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
            return $this->getUrl(self::CHECKOUT_ENDPOINT, $params);
        }
    }
}
