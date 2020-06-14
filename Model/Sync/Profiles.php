<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Apsis\One\Model\Sync\Profiles\Customers;
use Apsis\One\Model\Sync\Profiles\Batch;
use Magento\Store\Model\ScopeInterface;

class Profiles
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
    public function batchAndSyncProfiles(ApsisCoreHelper $apsisCoreHelper)
    {
        $stores = $apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $account = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            if ($account) {
                $this->subscribers->batchForStore($store, $apsisCoreHelper);
                $this->customers->batchForStore($store, $apsisCoreHelper);
                $this->batch->syncBatchItemsForStore($store, $apsisCoreHelper);
            }
        }
    }
}
