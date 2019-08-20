<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Widget\Button;

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
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Connect constructor.
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
     */
    public function _getElementHtml(AbstractElement $element)
    {
        try {
            $title = __('Connect');
            $url = '';

            return $this->getLayout()
                ->createBlock(Button::class)
                ->setType('button')
                ->setLabel($title)
                ->setDisabled(true)
                ->setOnClick("window.location.href='" . $url . "'")
                ->toHtml();
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__CLASS__, __METHOD__, $e->getMessage());
            return parent::_getElementHtml($element);
        }
    }
}
