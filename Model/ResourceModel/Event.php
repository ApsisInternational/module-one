<?php

namespace Apsis\One\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;

class Event extends AbstractDb
{
    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(Helper::APSIS_EVENT_TABLE, 'id');
    }

    /**
     * @param array $events
     *
     * @throws LocalizedException
     */
    public function insertEvents(array $events)
    {
        $write = $this->getConnection();
        if (! empty($events)) {
            $write->insertMultiple($this->getMainTable(), $events);
        }
    }
}
