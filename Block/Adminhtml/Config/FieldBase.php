<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class FieldBase extends Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $element->setData('readonly', 1)
            ->addClass('apsis-copy-helper');
        return parent::_getElementHtml($element);
    }
}
