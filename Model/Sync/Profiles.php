<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Batch;
use Apsis\One\Model\Sync\Profiles\Customers;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Profiles implements SyncInterface
{
    /**
     * @var Subscribers
     */
    private Subscribers $subscribers;

    /**
     * @var Customers
     */
    private Customers $customers;

    /**
     * @var Batch
     */
    private Batch $batch;

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
     * @inheritdoc
     */
    public function process(ApsisCoreHelper $apsisCoreHelper): void
    {
        foreach ($apsisCoreHelper->getStores() as $store) {
            try {
                $account = $apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
                if (! $account) {
                    continue;
                }

                //Validate file upload url host reachable
                $region = $apsisCoreHelper->getRegion(ScopeInterface::SCOPE_STORES, $store->getId());
                $apsisCoreHelper->validateIsUrlReachable($apsisCoreHelper->buildFileUploadHostName($region));

                //Start batch process for Subscribers
                $subscriberSync = (boolean) $apsisCoreHelper
                    ->getStoreConfig($store, ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENABLED);
                if ($subscriberSync) {
                    $this->subscribers->processForStore($store, $apsisCoreHelper);
                }

                //Start batch process for Customers
                $customerSync = (boolean) $apsisCoreHelper
                    ->getStoreConfig($store, ApsisConfigHelper::SYNC_SETTING_CUSTOMER_ENABLED);
                if ($customerSync) {
                    $this->customers->processForStore($store, $apsisCoreHelper);
                }

                //Start sync process for batch items (type Subscribers & Customers) in all states.
                if ($subscriberSync || $customerSync) {
                    $this->batch->processForStore($store, $apsisCoreHelper);
                }
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());

                continue;
            }
        }
    }
}
