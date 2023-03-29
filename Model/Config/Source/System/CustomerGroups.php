<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Customer\Model\Config\Source\Group;

class CustomerGroups extends Group
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return array_merge([['value' => 'N/A', 'label' => __('N/A')]], parent::toOptionArray());
    }
}
