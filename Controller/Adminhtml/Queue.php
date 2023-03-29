<?php

namespace Apsis\One\Controller\Adminhtml;

use Magento\Backend\App\Action;

abstract class Queue extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::queue';
}