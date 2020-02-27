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
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

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
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Subscribers $subscribers
     * @param Customers $customers
     * @param Batch $batch
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Subscribers $subscribers,
        Customers $customers,
        Batch $batch
    ) {
        $this->batch = $batch;
        $this->customers = $customers;
        $this->subscribers = $subscribers;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * Sync subscribers and customers
     */
    public function batchAndSyncProfiles()
    {
        $stores = $this->apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            if ($account) {
                $this->subscribers->batchForStore($store);
                $this->customers->batchForStore($store);
                $this->batch->syncBatchItemsForStore($store);
            }
        }
    }
}
