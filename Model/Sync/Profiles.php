<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Apsis\One\Model\Sync\Profiles\Customers;
use Apsis\One\Model\Sync\Profiles\Batch;
use Exception;
use Magento\Store\Model\ScopeInterface;

class Profiles implements SyncInterface
{
    /**
     * @var Subscribers
     */
    private $subscribers;

    /**
     * @var Customers
     */
    private $customers;

    /**
     * @var Batch
     */
    private $batch;

    /**
     * Profiles constructor.
     *
     * @param Subscribers $subscribers
     * @param Customers $customers
     * @param Batch $batch
     */
    public function __construct(
        Subscribers $subscribers,
        Customers $customers,
        Batch $batch
    ) {
        $this->batch = $batch;
        $this->customers = $customers;
        $this->subscribers = $subscribers;
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function process(ApsisCoreHelper $apsisCoreHelper)
    {
        $stores = $apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            try {
                $account = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
                if ($account) {
                    $this->subscribers->processForStore($store, $apsisCoreHelper);
                    $this->customers->processForStore($store, $apsisCoreHelper);
                    $this->batch->processForStore($store, $apsisCoreHelper);
                }
            } catch (Exception $e) {
                $apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                $apsisCoreHelper->log(__METHOD__ . ': Skipped profile sync for store :' . $store->getId());
                continue;
            }
        }
    }
}
