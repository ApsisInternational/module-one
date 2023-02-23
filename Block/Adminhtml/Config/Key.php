<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Key extends Field
{
    /**
     * @inheritdoc
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $element->setData('readonly', 1)
            ->addClass('apsis-copy-helper');
        return parent::_getElementHtml($element);
    }
}
