<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Store\Model\ScopeInterface;

class Find
{
    /**
     * @var AbandonedSub
     */
    private $abandonedSub;

    /**
     * Find constructor.
     *
     * @param AbandonedSub $abandonedSub
     */
    public function __construct(AbandonedSub $abandonedSub)
    {
        $this->abandonedSub = $abandonedSub;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function processAbandonedCarts(ApsisCoreHelper $apsisCoreHelper)
    {
        $stores = $apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $isEnabled = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            $acDelayPeriod = $apsisCoreHelper
                ->getStoreConfig($store, ApsisConfigHelper::CONFIG_APSIS_ONE_ABANDONED_CARTS_SEND_AFTER);

            if ($isEnabled && $acDelayPeriod) {
                $quoteCollection = $this->abandonedSub
                    ->getQuoteCollectionByStore($store, $acDelayPeriod, $apsisCoreHelper);
                if ($quoteCollection && $quoteCollection->getSize()) {
                    $this->abandonedSub->aggregateCartDataFromStoreCollection($quoteCollection, $apsisCoreHelper);
                }
            }
        }
    }
}
