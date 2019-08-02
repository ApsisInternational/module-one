<?php

namespace Apsis\One\Model\Config\Source\Abandoned;

use Magento\Framework\Data\OptionSourceInterface;

class Intervalminute implements OptionSourceInterface
{
    /**
     * Available times.
     *
     * @var array
     */
    protected $times = [15, 20, 25, 30, 45, 60, 90, 120, 180, 240];

    /**
     * @var string
     */
    protected $timeType = 'Minute';

    /**
     * Abandoned cart minutes options.
     *
     * @return array
     */
    public function toOptionArray()
    {
        //default data option
        $interval[] = ['value' => 0, 'label' => __('Disable')];

        foreach ($this->times as $time) {
            $interval[] =  ['value' => $time, 'label' => __($time . ' ' . $this->timeType)];
        }
        return $interval;
    }
}
