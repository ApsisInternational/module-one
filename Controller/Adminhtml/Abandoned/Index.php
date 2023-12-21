<?php

namespace Apsis\One\Controller\Adminhtml\Abandoned;

use Apsis\One\Controller\Adminhtml\AbstractIndex;

class Index extends AbstractIndex
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::abandoned';

    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Abandoned Carts';
    }
}
