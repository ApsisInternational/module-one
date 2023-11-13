<?php

namespace Apsis\One\Block\Adminhtml\Ui;

class WebhookButton extends AbstractButton
{
    /**
     * @inheritDoc
     */
    protected function getAction(): string
    {
        return 'apsis_one/webhook';
    }
}
