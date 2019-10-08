<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Apsis\One\Model\Sync\Profiles\Customers;

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
    public function syncProfiles()
    {
        $stores = $this->apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $account = (boolean) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED
            );
            if ($account) {
                $this->subscribers->sync($store);
                $this->customers->sync($store);
            }
        }
    }
}
