<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\AbstractModel;

interface ProfileDataInterface
{
    /**
     * @param array $mappingHash
     * @param AbstractModel $model
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return $this
     */
    public function setModelData(array $mappingHash, AbstractModel $model, ApsisCoreHelper $apsisCoreHelper);

    /**
     * @return array
     */
    public function toCSVArray();
}
