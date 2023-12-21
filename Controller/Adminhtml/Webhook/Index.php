<?php

namespace Apsis\One\Controller\Adminhtml\Webhook;

use Apsis\One\Controller\Adminhtml\AbstractIndex;

class Index extends AbstractIndex
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::queue';

    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Integration Webhooks';
    }
}
