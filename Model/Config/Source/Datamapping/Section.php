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
        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
        $apiClient = $this->apsisCoreHelper->getApiClient(
            $scope['context_scope'],
            $scope['context_scope_id']
        );
        if (! $apiClient) {
            return [['value' => '0', 'label' => __('-- Account Is Not Enabled Or Invalid Credentials --')]];
        }

        $request = $apiClient->getSections();
        if (! $request || ! isset($request->items)) {
            return [['value' => '0', 'label' => __('-- Invalid Request Or No Sections Exist On Account --')]];
        }

        $fields[] = ['value' => '0', 'label' => __('-- Please Select --')];
        foreach ($request->items as $section) {
            $fields[] = ['value' => $section->discriminator, 'label' => $section->name];
        }

        return $fields;
    }
}
