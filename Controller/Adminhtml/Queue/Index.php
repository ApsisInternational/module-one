<?php

namespace Apsis\One\Controller\Adminhtml\Queue;

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
        return 'DeltaSync Queue';
    }
}
