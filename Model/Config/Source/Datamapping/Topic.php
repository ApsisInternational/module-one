<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Helper\Config as ApsisConfigHelper;

class Topic implements OptionSourceInterface
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
        $mappedSectionFromScope = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_TOPIC_SECTION
        );
        if (! $mappedSectionFromScope) {
            return [['value' => '0', 'label' => __('-- Map & Save Section First --')]];
        }

        //default data option
        $fields[] = ['value' => '0', 'label' => __('-- Please Select --')];

        /**
         * @todo fetch from account set at selected scope
         */
        $fields[] = ['value' => 'topic1', 'label' => __('TopicOne')];
        $fields[] = ['value' => 'topic2', 'label' => __('TopicTwo')];

        return $fields;
    }
}
