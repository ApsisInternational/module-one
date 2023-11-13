<?php

namespace Apsis\One\Block\Adminhtml\Ui;

class QueueButton extends AbstractButton
{
    /**
     * @inheritDoc
     */
    protected function getAction(): string
    {
        return 'apsis_one/queue';
    }
}
