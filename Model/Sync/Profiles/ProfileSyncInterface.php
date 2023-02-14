<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Store\Api\Data\StoreInterface;

interface ProfileSyncInterface
{
    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function processForStore(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper): void;
}
