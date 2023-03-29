<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Webhook extends AbstractDb
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(ApsisCoreHelper::APSIS_WEBHOOK_TABLE, 'id');
    }
}
