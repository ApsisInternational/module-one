<?php

namespace Apsis\One\Controller\Adminhtml;

use Magento\Backend\App\Action;

abstract class Queue extends Action
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::queue';
}
