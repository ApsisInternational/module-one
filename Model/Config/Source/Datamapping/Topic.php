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
        $section = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        );
        if (! $section) {
            return [['value' => '0', 'label' => __('-- Map & Save Section First --')]];
        }

        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
        $apiClient = $this->apsisCoreHelper->getApiClient(
            $scope['context_scope'],
            $scope['context_scope_id']
        );
        if (! $apiClient) {
            return [['value' => '0', 'label' => __('-- Account Is Not Enabled Or Invalid Credentials --')]];
        }

        $consentLists = $apiClient->getConsentLists($section);
        if (! $consentLists || ! isset($consentLists->items)) {
            return [['value' => '0', 'label' => __('-- Invalid Request Or No Consent Lists On Account --')]];
        }

        //default data option
        $options[] = ['value' => '0', 'label' => __('-- Please Select --')];

        foreach ($consentLists->items as $consentList) {
            $topics = $apiClient->getTopics($section, $consentList->discriminator);
            if (! $topics || ! isset($topics->items)) {
                continue;
            }

            $formattedTopics = [];
            foreach ($topics->items as $topic) {
                $formattedTopics[] = [
                    'value' => $consentList->discriminator . '|' . $topic->discriminator,
                    'label' => $topic->name
                ];
            }
            $options[] = [
                'label' => $consentList->name,
                'value' => $formattedTopics
            ];
        }

        return $options;
    }
}
