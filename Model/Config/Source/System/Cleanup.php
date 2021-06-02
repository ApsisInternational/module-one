<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class Cleanup implements OptionSourceInterface
{
    /**
     * Available durations.
     *
     * @var array
     */
    protected $days = [7, 14, 30, 60, 90, 180];

    /**
     * @var string
     */
    protected $timeType = 'Days';

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $interval = [['value' => 0, 'label' => __('Disable')]];

        foreach ($this->days as $day) {
            $interval[] =  ['value' => $day, 'label' => __($day . ' ' . $this->timeType)];
        }

        return $interval;
    }
}
