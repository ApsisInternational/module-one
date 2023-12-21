<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

class IsStatusSourceModel extends AbstractOptionsSource
{
    /**
     * @inheritdoc
     */
    protected function getOptionTextMap(): array
    {
        return ['0' => 'No', '1' => 'Yes'];
    }
}
