<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * @todo fetch from account set at selected scope
 */
class Section implements OptionSourceInterface
{
    /**
     *  Attribute options
     *
     * @return array
     */
    public function toOptionArray()
    {
        //default data option
        $fields[] = ['value' => '0', 'label' => __('-- Please Select --')];

        $fields[] = ['value' => 'section1', 'label' => __('SectionOne')];
        $fields[] = ['value' => 'section2', 'label' => __('SectionTwo')];

        return $fields;
    }
}
