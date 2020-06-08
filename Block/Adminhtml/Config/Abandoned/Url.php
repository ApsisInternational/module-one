<?php

namespace Apsis\One\Block\Adminhtml\Config\Abandoned;

use Magento\Config\Block\System\Config\Form\Field;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Url extends Field
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Url constructor.
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
        $text = sprintf(
            '%sapsis/abandoned/cart/token/TOKEN/output/OUTPUT_TYPE/limit/NUMBER_LIMIT',
            $this->apsisCoreHelper->generateBaseUrlForDynamicContent()
        );
        $element->setData('value', $text);
        return parent::_getElementHtml($element);
    }
}
