<?php

namespace Apsis\One\Block\Adminhtml\Ui;

use Magento\Backend\Block\Template;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;

class InfoGroupBlock extends Fieldset
{
    /**
     * @inheirtDoc
     */
    public function render(AbstractElement $element): string
    {
        return $this->_layout
            ->createBlock(Template::class)
            ->setTemplate('Apsis_One::info.phtml')
            ->toHtml();
    }
}
