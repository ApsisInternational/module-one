<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;

interface ResourceInterface
{
    /**
     * @param ApsisLogHelper $apsisLogHelper
     *
     * @return boolean
     */
    public function truncateTable(ApsisLogHelper $apsisLogHelper): bool;

    /**
     * @param int $day
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function cleanupRecords(int $day, ApsisCoreHelper $apsisCoreHelper): void;
}
