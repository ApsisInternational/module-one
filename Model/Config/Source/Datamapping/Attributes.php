<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Registry;
use Throwable;

class Attributes implements OptionSourceInterface
{
    const REGISTRY_NAME = 'apsis_attributes';

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var Registry
     */
    private Registry $registry;

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
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $fields = [['value' => '', 'label' => __('-- Please Select --')]];

        try {
            $section = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION
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
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $fields;
    }
}
