<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Apsis\One\Model\Sync\Profiles\Customers;
use Apsis\One\Model\Sync\Profiles\Batch;
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
            $account = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            if ($account) {
                $this->subscribers->processForStore($store, $apsisCoreHelper);
                $this->customers->processForStore($store, $apsisCoreHelper);
                $this->batch->processForStore($store, $apsisCoreHelper);
            }
        }
    }
}