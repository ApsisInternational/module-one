<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Framework\Registry;

class Attributes implements OptionSourceInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * Attributes constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Registry $registry
     */
    public function __construct(ApsisCoreHelper $apsisCoreHelper, Registry $registry)
    {
        $this->registry = $registry;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     *  Attribute options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $section = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        );
        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
        $apiClient = $this->apsisCoreHelper->getApiClient(
            $scope['context_scope'],
            $scope['context_scope_id']
        );
        if (! $apiClient || ! $section) {
            return [];
        }

        $savedAttributes = $this->registry->registry('apsis_attributes');
        if ($savedAttributes) {
            $attributes = $savedAttributes;
        } else {
            $attributes = $apiClient->getAttributes($section);
            $this->registry->unregister('apsis_attributes');
            $this->registry->register('apsis_attributes', $attributes);
        }

        if (! $attributes || ! isset($attributes->items)) {
            return [];
        }

        $fields[] = ['value' => '', 'label' => __('-- Please Select --')];
        foreach ($attributes->items as $attribute) {
            $fields[] = ['value' => $attribute->discriminator, 'label' => $attribute->name];
        }

        return $fields;
    }
}