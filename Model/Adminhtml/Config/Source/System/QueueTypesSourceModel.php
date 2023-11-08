<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Apsis\One\Model\QueueModel;

class QueueTypesSourceModel extends AbstractOptionsSource
{
    /**
     * @inheritdoc
     */
    protected function getOptionTextMap(): array
    {
        return QueueModel::TYPE_TEXT_MAP;
    }
}
