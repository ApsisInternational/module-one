<?php

namespace Apsis\One\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as ApsisCoreHelper;

class ProfileBatch extends AbstractDb
{
    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_PROFILE_BATCH_TABLE, 'id');
    }
}
