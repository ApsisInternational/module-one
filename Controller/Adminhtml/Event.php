<?php

namespace Apsis\One\Controller\Adminhtml;

use Magento\Backend\App\Action;

abstract class Event extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::event';
}
