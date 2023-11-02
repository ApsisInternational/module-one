<?php

namespace Apsis\One\Controller\Adminhtml\Config;

use Apsis\One\Controller\Adminhtml\AbstractIndex;

class Index extends AbstractIndex
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::config';

    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Integration Configs';
    }
}
