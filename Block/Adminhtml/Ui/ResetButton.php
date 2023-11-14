<?php

namespace Apsis\One\Block\Adminhtml\Ui;

class ResetButton extends AbstractButton
{
    /**
     * @inheritDoc
     */
    protected function getAction(): string
    {
        return 'apsis_one/developer/reset';
    }
}
