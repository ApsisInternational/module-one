<?php

namespace Apsis\One\Block\Adminhtml\Ui;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

abstract class AbstractButton extends Field
{
    /**
     * @return string
     */
    abstract protected function getAction(): string;

    /**
     * @inheirtDoc
     */
    public function render(AbstractElement $element): string
    {
        return $this->_layout
            ->createBlock(Button::class)
            ->addData(
                [
                    'label' => __($element->getLabel()),
                    'onclick' => sprintf("location.href='%s'", $this->getUrl(static::getAction())),
                    'id' => $element->getId(),
                    'class' => 'apsis-resource-button',
                ]
            )
            ->toHtml();
    }
}
