<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class CronSchedule implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '*/1 * * * *', 'label' => 'Every 1 Minutes. Not recommended, strictly for testing'],
            ['value' => '*/5 * * * *', 'label' => 'Every 5 Minutes'],
            ['value' => '*/10 * * * *', 'label' => 'Every 10 Minutes'],
            ['value' => '*/15 * * * *', 'label' => 'Every 15 Minutes'],
        ];
    }
}
