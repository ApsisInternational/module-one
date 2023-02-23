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
}
