<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * @todo fetch from account set at selected scope
 */
class Attributes implements OptionSourceInterface
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

        $fields[] = ['value' => 'fname', 'label' => __('FirstName')];
        $fields[] = ['value' => 'lname', 'label' => __('LastName')];

        return $fields;
    }
}
