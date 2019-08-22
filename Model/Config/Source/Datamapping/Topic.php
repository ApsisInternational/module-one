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

    /**
     * Topic constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     */
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
        if (! $this->apsisCoreHelper->isEnabledForSelectedScopeInAdmin()) {
            return [['value' => '0', 'label' => __('-- Please Enable Account First --')]];
        }

        if (! $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        )) {
            return [['value' => '0', 'label' => __('-- Map & Save Section First --')]];
        }

        //default data option
        $options[] = ['value' => '0', 'label' => __('-- Please Select --')];

        /**
         * @todo fetch from section / account set at selected scope
         */
        $options[] = [
            'label' => 'Consent list 1',
            'value' => [
                ['value' => 'consent1_topic1', 'label' => 'Topic 1'],
                ['value' => 'consent1_topic2', 'label' => 'Topic 2']
            ]
        ];
        $options[] = [
            'label' => 'Consent list 2',
            'value' => [
                ['value' => 'consent2_topic1', 'label' => 'Topic 1'],
                ['value' => 'consent2_topic2', 'label' => 'Topic 2']
            ]
        ];

        return $options;
    }
}
