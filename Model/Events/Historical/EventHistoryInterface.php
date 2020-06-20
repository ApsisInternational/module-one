<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\ResourceModel\Profile\Collection as ProfileCollection;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Store\Api\Data\StoreInterface;

interface EventHistoryInterface
{
    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollection $profileCollection
     * @param array $duration
     *
     * @return void
     */
    public function fetchForStore(
        StoreInterface $store,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollection $profileCollection,
        array $duration
    );
}
