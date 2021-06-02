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
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => '*/5 * * * *', 'label' => __('Every 5 Minutes')],
            ['value' => '*/10 * * * *', 'label' => __('Every 10 Minutes')],
            ['value' => '*/15 * * * *', 'label' => __('Every 15 Minutes')]
        ];

        if ($this->state->getMode() !== State::MODE_PRODUCTION) {
            $options[] = [
                'value' => '*/1 * * * *',
                'label' => __('Every 1 Minutes. Not recommended, strictly for testing')
            ];
        }

        return $options;
    }
}
