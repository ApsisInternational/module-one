<?php

namespace Apsis\One\Block\Adminhtml\Config\Abandoned;

use Apsis\One\Helper\Config as ApsisConfigHelper;
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
        $baseUrl = $this->apsisCoreHelper->generateBaseUrlForDynamicContent();
        $mappedAttributeForLastQuoteId = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_LAST_QUOTE_ID
        );
        $mappedAttributeForAcToken = $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_CUSTOMER_AC_TOKEN
        );

        if (! $mappedAttributeForLastQuoteId) {
            $mappedAttributeForLastQuoteId = __('PLEASE MAP LAST CART ID ATTRIBUTE');
        }

        if (! $mappedAttributeForAcToken) {
            $mappedAttributeForAcToken = __('PLEASE MAP AC TOKEN ATTRIBUTE');
        }

        $text = sprintf(
            '%sapsis/abandoned/cart/token/##%s##/quote_id/##%s##',
            $baseUrl,
            $mappedAttributeForAcToken,
            $mappedAttributeForLastQuoteId
        );

        $element->setData('value', $text);
        return parent::_getElementHtml($element);
    }
}
