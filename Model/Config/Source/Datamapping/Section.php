<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Data\OptionSourceInterface;

class Section implements OptionSourceInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Section constructor.
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
