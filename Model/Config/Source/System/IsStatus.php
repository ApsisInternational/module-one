<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class IsStatus implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => '0',
                'label' => 'No'
            ],
            [
                'value' => '1',
                'label' => 'Yes',
            ]
        ];

        return $options;
    }
}
