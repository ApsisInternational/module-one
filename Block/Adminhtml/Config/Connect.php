<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Exception\LocalizedException;

/**
 * @todo provide OAUTH Logic at selected scope level
 */
class Connect extends Field
{
    /**
     * @var string
     */
    public $buttonLabel = 'Connect';

    /**
     * @param string $buttonLabel
     *
     * @return $this
     */
    public function setButtonLabel($buttonLabel)
    {
        $this->buttonLabel = $buttonLabel;
        return $this;
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     *
     * @throws LocalizedException
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $title = __('Connect');
        $url = '';

        return $this->getLayout()
            ->createBlock(Button::class)
            ->setType('button')
            ->setLabel($title)
            ->setDisabled(true)
            ->setOnClick("window.location.href='" . $url . "'")
            ->toHtml();
    }
}
