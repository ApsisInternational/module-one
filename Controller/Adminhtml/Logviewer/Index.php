<?php

namespace Apsis\One\Controller\Adminhtml\Logviewer;

use Apsis\One\Controller\Adminhtml\AbstractIndex;

class Index extends AbstractIndex
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::logviewer';

    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Logs';
    }
}
