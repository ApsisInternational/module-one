<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use \Magento\Framework\Model\AbstractModel;

interface EventDataInterface
{
    /**
     * @param AbstractModel $model
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return array
     */
    public function getProcessedDataArr(AbstractModel $model, ApsisCoreHelper $apsisCoreHelper);
}
