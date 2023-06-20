<?php

namespace Apsis\One\Controller\Adminhtml\Event;

use Apsis\One\Controller\Adminhtml\AbstractIndex;

class Index extends AbstractIndex
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::event';

    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Events';
    }
}
