<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Apsis\One\Model\Sync\Profiles\Customers;
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
     * Profiles constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Subscribers $subscribers
     * @param Customers $customers
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Subscribers $subscribers,
        Customers $customers
    ) {
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
                $this->subscribers->batch($store);
                $this->subscribers->syncBatchItems($store);
                $this->customers->batch($store);
                $this->customers->syncBatchItems($store);
            }
        }
    }
}
