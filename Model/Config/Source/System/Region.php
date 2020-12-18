<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\State;

class Region implements OptionSourceInterface
{
    /**
     * API Regions
     */
    const REGION_EU = 'api';
    const REGION_APAC = 'api-apac';
    const REGION_STAGE = 'api-stage';

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
        $options[] = ['value' => '', 'label' => __('Please select region')];
        if ($this->state->getMode() !== State::MODE_PRODUCTION) {
            $options[] = ['value' => self::REGION_STAGE, 'label' => 'Stage'];
        }
        $options[] = ['value' => self::REGION_EU, 'label' => 'EU'];
        $options[] = ['value' => self::REGION_APAC, 'label' => 'APAC'];
        return $options;
    }
}
