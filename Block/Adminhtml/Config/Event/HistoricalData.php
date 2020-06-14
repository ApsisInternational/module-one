<?php

namespace Apsis\One\Block\Adminhtml\Config\Event;

use Magento\Config\Block\System\Config\Form\Field;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Helper\Config as ApsisConfigHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class HistoricalData extends Field
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * HistoricalData constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        array $data = []
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $isDoneFlag = (boolean) $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DONE_FLAG
        );
        if ($element->getValue() && $isDoneFlag) {
            $element->setDisabled('disabled');
        }
        return parent::_getElementHtml($element);
    }
}
