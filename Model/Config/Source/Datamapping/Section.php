<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
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
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $fields = [['value' => '', 'label' => __('-- Please Select --')]];

        try {
            $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();

            if (! $this->apsisCoreHelper->isEnabled($scope['context_scope'], $scope['context_scope_id'])) {
                return [];
            }

            $apiClient = $this->apsisCoreHelper->getApiClient(
                $scope['context_scope'],
                $scope['context_scope_id']
            );
            if (! $apiClient) {
                return [];
            }

            $request = $apiClient->getSections();
            if (! $request || ! isset($request->items)) {
                $this->apsisCoreHelper->log(__METHOD__ . ': No section found on account. Try again.');
                return [];
            }

            foreach ($request->items as $section) {
                $fields[] = ['value' => $section->discriminator, 'label' => $section->name];
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $fields;
    }
}
