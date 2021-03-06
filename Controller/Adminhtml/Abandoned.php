<?php

namespace Apsis\One\Controller\Adminhtml;

use Magento\Backend\App\Action;

abstract class Abandoned extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::abandoned';
}
