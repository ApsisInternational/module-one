<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class HistoricalEventDurationSelect extends Field
{
    /**
     * @inheirtDoc
     */
    public function _decorateRowHtml(AbstractElement $element, $html): string
    {
        $html .= '<td><button title="Duration" type="submit" class="primary"><span>Set Duration</span></button></td>';
        return '<tr id="row_' . $element->getHtmlId() . '">' . $html . '</tr>';
    }
}
