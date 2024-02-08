<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class IntervalMinuteSourceModel implements OptionSourceInterface
{
    /**
     * @var array
     */
    protected array $times = [0, 15, 20, 25, 30, 45, 60, 90, 120, 180, 240];

    /**
     * @var string
     */
    protected string $timeType = 'Minute';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $interval = [];

        foreach ($this->times as $time) {
            $interval[] =  ['value' => $time, 'label' => __($time ? $time . ' ' . $this->timeType : 'Disable')];
        }

        return $interval;
    }
}
