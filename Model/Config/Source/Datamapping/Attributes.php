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

    /**
     * Attributes constructor.
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
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_TOPIC_SECTION
        )) {
            return [['value' => '0', 'label' => __('-- Map & Save Section First --')]];
        }

        //default data option
        $fields[] = ['value' => '0', 'label' => __('-- Please Select --')];

        /**
         * @todo fetch from account set at selected scope
         */
        $fields[] = ['value' => 'fname', 'label' => 'First Name'];
        $fields[] = ['value' => 'lname', 'label' => 'Last Name'];

        return $fields;
    }
}
