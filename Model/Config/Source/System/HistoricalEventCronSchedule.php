<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\App\State;
use Magento\Framework\Data\OptionSourceInterface;

class HistoricalEventCronSchedule extends CronSchedule implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        if ($this->state->getMode() !== State::MODE_PRODUCTION) {
            $options[] = [
                'value' => '*/15 * * * *',
                'label' => __('Every 15 Minutes. Not recommended, strictly for testing')
            ];
        }
        $options[] = ['value' => '0 0 * * *', 'label' => __('Everyday 12AM')];
        return $options;
    }
}
