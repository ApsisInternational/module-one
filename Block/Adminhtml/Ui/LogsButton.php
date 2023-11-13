<?php

namespace Apsis\One\Block\Adminhtml\Ui;

class LogsButton extends AbstractButton
{
    /**
     * @inheritDoc
     */
    protected function getAction(): string
    {
        return 'apsis_one/logviewer';
    }
}
