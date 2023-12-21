<?php

namespace Apsis\One\Block\Adminhtml\Ui;

class ConfigButton extends AbstractButton
{
    /**
     * @inheritDoc
     */
    protected function getAction(): string
    {
        return 'apsis_one/config';
    }
}
