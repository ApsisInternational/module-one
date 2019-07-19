<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;

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

        /**
         * @todo fetch from account set at selected scope
         */
        $fields[] = ['value' => 'section1', 'label' => 'Section One'];
        $fields[] = ['value' => 'section2', 'label' => 'Section Two'];

        return $fields;
    }
}
