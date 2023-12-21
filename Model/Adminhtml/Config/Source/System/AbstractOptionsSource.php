<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;

abstract class AbstractOptionsSource implements OptionSourceInterface
{
    /**
     * @return array
     */
    abstract protected function getOptionTextMap(): array;

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (static::getOptionTextMap() as $value => $label) {
            $options[] = ['value' => $value, 'label' => __($label)];
        }
        return $options;
    }
}
