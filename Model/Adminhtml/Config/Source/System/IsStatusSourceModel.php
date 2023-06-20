<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

class IsStatusSourceModel implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => __('No')],
            ['value' => '1', 'label' => __('Yes')]
        ];
    }
}
