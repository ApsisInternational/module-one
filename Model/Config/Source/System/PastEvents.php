<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class PastEvents implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Disabled')],
            ['value' => 1, 'label' => __('1 Month')],
            ['value' => 3, 'label' => __('3 Month')],
            ['value' => 6, 'label' => __('6 Month')],
            ['value' => 12, 'label' => __('1 Year')],
            ['value' => 24, 'label' => __('2 Year')]
        ];
    }
}
