<?php

namespace Apsis\One\Block\Adminhtml\Config\Abandoned;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Block\Adminhtml\Config\FieldBase;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Url extends FieldBase
{
    const APSIS_CART_EXPOSE_ENDPOINT = 'apsis/abandoned/cart';

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
            '%s%s',
            $this->apsisCoreHelper->generateBaseUrlForDynamicContent(),
            self::APSIS_CART_EXPOSE_ENDPOINT
        );
        $element->setData('value', $text);
        return parent::_getElementHtml($element);
    }
}
