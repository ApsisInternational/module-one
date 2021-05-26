<?php

namespace Apsis\One\Block\Adminhtml\Config\Abandoned;

use Apsis\One\Block\Adminhtml\Config\FieldBase;
use Apsis\One\Model\Service\Log;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Url extends FieldBase
{
    const APSIS_CART_EXPOSE_ENDPOINT = 'apsis/abandoned/cart';

    /**
     * Url constructor.
     *
     * @param Context $context
     * @param Log $logger
     * @param array $data
     */
    public function __construct(Context $context, Log $logger, array $data = [])
    {
        parent::__construct($context, $logger, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $text = sprintf(
            '%s%s',
            $this->generateBaseUrlForDynamicContent(),
            self::APSIS_CART_EXPOSE_ENDPOINT
        );
        $element->setData('value', $text);
        return parent::_getElementHtml($element);
    }
}
