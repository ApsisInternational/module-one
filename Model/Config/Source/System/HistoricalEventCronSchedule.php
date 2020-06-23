<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class HistoricalEventCronSchedule implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '*/15 * * * *', 'label' => 'Every 15 Minutes. Not recommended, strictly for testing'],
            ['value' => '0 0 * * *', 'label' => 'Everyday 12AM']
        ];
    }
}
