<?php

namespace Apsis\One\Model\Abandoned;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Throwable;

class Find
{
    /**
     * @var AbandonedSub
     */
    private AbandonedSub $abandonedSub;

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
     *
     * @return void
     */
    public function process(ApsisCoreHelper $apsisCoreHelper): void
    {
        try {
            $stores = $apsisCoreHelper->getStores();

            foreach ($stores as $store) {
                try {
                    $acDelayPeriod = $apsisCoreHelper->getStoreConfig(
                        $store,
                        ApsisCoreHelper::PATH_CONFIG_AC_DURATION
                    );
                    if (! $acDelayPeriod) {
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
                } catch (Throwable $e) {
                    $apsisCoreHelper->logError(__METHOD__, $e);
                    $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
                    continue;
                }
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
