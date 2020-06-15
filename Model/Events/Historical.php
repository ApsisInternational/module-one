<?php

namespace Apsis\One\Model\Events;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Store\Model\ScopeInterface;
use Apsis\One\Model\Events\Historical\Carts;
use Apsis\One\Model\Events\Historical\Orders;
use Apsis\One\Model\Events\Historical\Reviews;

class Historical
{
    /**
     * @var Carts
     */
    private $historicalCarts;

    /**
     * @var Orders
     */
    private $historicalOrders;

    /**
     * @var Reviews
     */
    private $historicalReviews;

    /**
     * Historical constructor.
     *
     * @param Carts $historicalCarts
     * @param Orders $historicalOrders
     * @param Reviews $historicalReviews
     */
    public function __construct(
        Carts $historicalCarts,
        Orders $historicalOrders,
        Reviews $historicalReviews
    ) {
        $this->historicalCarts = $historicalCarts;
        $this->historicalOrders = $historicalOrders;
        $this->historicalReviews = $historicalReviews;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function processHistoricalEvents(ApsisCoreHelper $apsisCoreHelper)
    {
        $stores = $apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $account = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            $pastEventsDuration = $apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DURATION
            );
            $doneFlag = (boolean) $apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DONE_FLAG
            );
            if ($account && $pastEventsDuration && ! $doneFlag) {
                //run each type and check event enabled for each type
            }
        }
    }
}
