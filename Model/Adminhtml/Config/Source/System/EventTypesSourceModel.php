<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Model\EventModel;

class EventTypesSourceModel extends AbstractOptionsSource
{
    /**
     * @inheritdoc
     */
    protected function getOptionTextMap(): array
    {
        return EventModel::TYPE_TEXT_MAP;
    }
}
