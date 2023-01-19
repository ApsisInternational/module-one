<?php

namespace Apsis\One\Model\ResourceModel\Cron;

use Magento\Cron\Model\Schedule;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Cron\Model\ResourceModel\Schedule as ScheduleResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_idFieldName = 'schedule_id';
        $this->_init(Schedule::class, ScheduleResource::class);
    }
}
