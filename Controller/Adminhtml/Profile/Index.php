<?php

namespace Apsis\One\Controller\Adminhtml\Profile;

use Apsis\One\Controller\Adminhtml\AbstractIndex;

class Index extends AbstractIndex
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::profile';

    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Profiles';
    }
}
