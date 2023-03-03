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
    protected array $times = [15, 20, 25, 30, 45, 60, 90, 120, 180, 240];

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
            $interval[] =  ['value' => $time, 'label' => __($time . ' ' . $this->timeType)];
        }

        return $interval;
    }
}
