<?php

namespace Apsis\One\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;

class Event extends AbstractDb
{
    /**
     * Initialize resource.
     *
     * @return null
     */
    public function _construct()
    {
        $this->_init(Helper::APSIS_EVENT_TABLE, 'id');
    }
}