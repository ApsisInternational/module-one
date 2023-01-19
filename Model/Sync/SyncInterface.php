<?php

namespace Apsis\One\Model\Sync;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;

interface SyncInterface
{
    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function process(ApsisCoreHelper $apsisCoreHelper): void;
}
