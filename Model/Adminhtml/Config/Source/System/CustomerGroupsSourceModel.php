<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Customer\Model\Config\Source\Group;

class CustomerGroupsSourceModel extends Group
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return array_merge([['value' => 'N/A', 'label' => __('N/A')]], parent::toOptionArray());
    }
}
