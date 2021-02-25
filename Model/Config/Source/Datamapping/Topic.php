<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Exception;
use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;

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
        $options = [];
        try {
            $section = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
            $apiClient = $this->apsisCoreHelper->getApiClient(
                $scope['context_scope'],
                $scope['context_scope_id']
            );
            if (! $apiClient || ! $section) {
                return $options;
            }

            $consentLists = $apiClient->getConsentLists($section);
            if (! $consentLists || ! isset($consentLists->items)) {
                $this->apsisCoreHelper->log(__METHOD__ . ': No consent list / topic found on section ' . $section);
                return $options;
            }

            $options[] = ['label' => __('--- Please select ---'), 'value' => ''];
            foreach ($consentLists->items as $consentList) {
                $topics = $apiClient->getTopics($section, $consentList->discriminator);
                if (! $topics || ! isset($topics->items)) {
                    continue;
                }

                $formattedTopics = [];
                foreach ($topics->items as $topic) {
                    $formattedTopics[] = [
                        'value' => $consentList->discriminator . '|' . $topic->discriminator . '|'
                            . $consentList->name . '|' . $topic->name,
                        'label' => $topic->name
                    ];
                }
                $options[] = [
                    'label' => $consentList->name,
                    'value' => $formattedTopics
                ];
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $options;
    }
}
