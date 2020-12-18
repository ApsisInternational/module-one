<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Exception;
use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Framework\Registry;

class Attributes implements OptionSourceInterface
{
    const REGISTRY_NAME = 'apsis_attributes';

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
        $fields[] = ['value' => '', 'label' => __('-- Please Select --')];
        try {
            $section = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            if (! $section) {
                return [];
            }

            $savedAttributes = $this->registry->registry(self::REGISTRY_NAME);
            if ($savedAttributes) {
                $attributes = $savedAttributes;
            } else {
                $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
                $apiClient = $this->apsisCoreHelper->getApiClient(
                    $scope['context_scope'],
                    $scope['context_scope_id']
                );
                if (! $apiClient) {
                    return [];
                }
                $attributes = $apiClient->getAttributes($section);
                $this->registry->unregister(self::REGISTRY_NAME);
                $this->registry->register(self::REGISTRY_NAME, $attributes, true);
            }

            if (! $attributes || ! isset($attributes->items)) {
                return [];
            }

            foreach ($attributes->items as $attribute) {
                $fields[] = ['value' => $attribute->discriminator, 'label' => $attribute->name];
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $fields;
    }
}
