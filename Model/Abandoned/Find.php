<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\SyncInterface;
use Exception;
use Magento\Store\Model\ScopeInterface;

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
                    $acDelayPeriod = $apsisCoreHelper->getStoreConfig(
                        $store,
                        ApsisConfigHelper::EVENTS_REGISTER_ABANDONED_CART_AFTER_DURATION
                    );
                    if (! $isEnabled || ! $acDelayPeriod) {
                        continue;
                    }


                    $quoteCollection = $this->abandonedSub
                        ->getQuoteCollectionByStore($store, $acDelayPeriod, $apsisCoreHelper);
                    if ($quoteCollection && $quoteCollection->getSize()) {
                        $this->abandonedSub->aggregateCartDataFromStoreCollection(
                            $quoteCollection,
                            $apsisCoreHelper,
                            $store->getId()
                        );
                    }
                } catch (Exception $e) {
                    $apsisCoreHelper->logError(__METHOD__, $e);
                    $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
                    continue;
                }
            }
        } catch (Exception $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
