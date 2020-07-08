<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Store\Model\ScopeInterface;
use Apsis\One\Model\Sync\SyncInterface;

class Find implements SyncInterface
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
    public function process(ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $stores = $apsisCoreHelper->getStores();
            foreach ($stores as $store) {
                try {
                    $isEnabled = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
                    $acDelayPeriod = $apsisCoreHelper
                        ->getStoreConfig($store, ApsisConfigHelper::CONFIG_APSIS_ONE_ABANDONED_CARTS_SEND_AFTER);
                    if ($isEnabled && $acDelayPeriod) {
                        $quoteCollection = $this->abandonedSub
                            ->getQuoteCollectionByStore($store, $acDelayPeriod, $apsisCoreHelper);
                        if ($quoteCollection && $quoteCollection->getSize()) {
                            $this->abandonedSub
                                ->aggregateCartDataFromStoreCollection($quoteCollection, $apsisCoreHelper);
                        }
                    }
                } catch (Exception $e) {
                    $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                    $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: '. $store->getId());
                    continue;
                }
            }
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }
}
