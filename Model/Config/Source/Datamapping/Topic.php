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

            $topics = $apiClient->getTopics($section);
            if (! $topics || ! isset($topics->items)) {
                $this->apsisCoreHelper->log(
                    __METHOD__ . ': No topic found on section ' . $section . '. Try again later.'
                );
                return [];
            }

            $options = [['label' => __('--- Please select ---'), 'value' => '']];

            foreach ($topics->items as $topic) {
                $options[] = [
                    'label' => $topic->name, 'value' => $topic->discriminator . '|' . $topic->name
                ];
            }
            return $options;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
