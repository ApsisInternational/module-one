<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class Region implements OptionSourceInterface
{
    /**
     * API Regions
     */
    const REGION_EU = 'api';
    const REGION_APAC = 'api-apac';
    const REGION_STAGE = 'api-stage';

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => '', 'label' => __('Please select region')],
            ['value' => self::REGION_EU, 'label' => 'EU'],
            ['value' => self::REGION_APAC, 'label' => 'APAC']
        ];

        if ((bool) getenv('APSIS_DEVELOPER')) {
            $options[] = ['value' => self::REGION_STAGE, 'label' => 'Stage'];
        }

        return $options;
    }
}
