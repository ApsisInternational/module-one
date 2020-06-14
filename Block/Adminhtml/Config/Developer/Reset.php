<?php

namespace Apsis\One\Block\Adminhtml\Config\Developer;

use Exception;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Apsis\One\Helper\Log as ApsisLogHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Reset extends Field
{
    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * @var string
     */
    public $buttonLabel = 'Reset';

    /**
     * @param string $buttonLabel
     *
     * @return $this
     */
    public function setButtonLabel(string $buttonLabel)
    {
        $this->buttonLabel = $buttonLabel;
        return $this;
    }

    /**
     * Url constructor.
     *
     * @param Context $context
     * @param ApsisLogHelper $apsisLogHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ApsisLogHelper $apsisLogHelper,
        array $data = []
    ) {
        $this->apsisLogHelper = $apsisLogHelper;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        try {
            $resetUrl = $this->escapeUrl($this->_urlBuilder->getUrl('apsis_one/developer/reset'));
            return $this->getLayout()
                ->createBlock(Button::class)
                ->setType('button')
                ->setOnClick("window.location.href='".$resetUrl."'")
                ->setLabel($this->buttonLabel)
                ->toHtml();
        } catch (Exception $e) {
            $this->apsisLogHelper->logMessage(__METHOD__, $e->getMessage());
            return parent::_getElementHtml($element);
        }
    }
}
