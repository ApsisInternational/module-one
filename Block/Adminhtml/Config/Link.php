<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Apsis\One\Model\Service\Core;

class Link extends Field
{
    /**
     * @var Core
     */
    private Core $coreHelper;

    /**
     * FieldBase constructor.
     *
     * @param Context $context
     * @param Core $corehelper
     * @param array $data
     */
    public function __construct(Context $context, Core $corehelper, array $data = [])
    {
        $this->coreHelper = $corehelper;
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $element->setData('value', $this->coreHelper->generateSystemAccessUrl($this->getRequest()))
            ->setData('readonly', 1)
            ->addClass('apsis-copy-helper');
        return parent::_getElementHtml($element);
    }
}
