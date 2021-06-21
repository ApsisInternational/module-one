<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Throwable;
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
     * @inheritdoc
     */
    public function toOptionArray()
    {
        try {
            $section = $this->apsisCoreHelper
                ->getMappedValueFromSelectedScope(ApsisConfigHelper::MAPPINGS_SECTION_SECTION);
            if (! $section) {
                return [];
            }

            $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
            $apiClient = $this->apsisCoreHelper->getApiClient($scope['context_scope'], $scope['context_scope_id']);
            if (! $apiClient) {
                return [];
            }

            $consentLists = $apiClient->getConsentLists($section);
            if (! $consentLists || ! isset($consentLists->items)) {
                $this->apsisCoreHelper->log(
                    __METHOD__ . ': No consent list / topic found on section ' . $section . '. Try again later.'
                );
                return [];
            }

            $options = [['label' => __('--- Please select ---'), 'value' => '']];

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

                $options[] = ['label' => $consentList->name, 'value' => $formattedTopics];
            }
            return $options;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
