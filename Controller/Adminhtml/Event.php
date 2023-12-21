<?php

namespace Apsis\One\Controller\Adminhtml;

use Magento\Backend\App\Action;

abstract class Event extends Action
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Apsis_One::event';
}
