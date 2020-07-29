<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\State;

class CronSchedule implements OptionSourceInterface
{
    /**
     * @var State
     */
    protected $state;

    /**
     * CronSchedule constructor.
     *
     * @param State $state
     */
    public function __construct(State $state)
    {
        $this->state = $state;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        if ($this->state->getMode() !== State::MODE_PRODUCTION) {
            $options[] = [
                'value' => '*/1 * * * *',
                'label' => 'Every 1 Minutes. Not recommended, strictly for testing'
            ];
        }
        $options[] = ['value' => '*/5 * * * *', 'label' => 'Every 5 Minutes'];
        $options[] = ['value' => '*/10 * * * *', 'label' => 'Every 10 Minutes'];
        $options[] = ['value' => '*/15 * * * *', 'label' => 'Every 15 Minutes'];
        return $options;
    }
}
