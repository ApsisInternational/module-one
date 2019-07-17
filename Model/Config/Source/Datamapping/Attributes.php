<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Helper\Config as ApsisConfigHelper;

class Attributes implements OptionSourceInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    public function __construct(ApsisCoreHelper $apsisCoreHelper)
    {
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     *  Attribute options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $mappedTopicFromScope = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_TOPIC_TOPIC
        );
        if (! $mappedTopicFromScope) {
            return [['value' => '0', 'label' => __('-- Map & Save Topic First --')]];
        }

        //default data option
        $fields[] = ['value' => '0', 'label' => __('-- Please Select --')];

        /**
         * @todo fetch from account set at selected scope
         */
        $fields[] = ['value' => 'fname', 'label' => __('FirstName')];
        $fields[] = ['value' => 'lname', 'label' => __('LastName')];

        return $fields;
    }
}
