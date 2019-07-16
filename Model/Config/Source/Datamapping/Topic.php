<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * @todo fetch from account set at selected scope. Change topic to multi level consent list -> topics
 */
class Topic implements OptionSourceInterface
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

        $fields[] = ['value' => 'topic1', 'label' => __('TopicOne')];
        $fields[] = ['value' => 'topic2', 'label' => __('TopicTwo')];

        return $fields;
    }
}
