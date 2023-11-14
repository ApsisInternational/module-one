<?php

namespace Apsis\One\Block\Adminhtml\Ui;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\App\State;

abstract class AbstractButton extends Field
{
    /**
     * @var State
     */
    private State $state;

    /**
     * @param Context $context
     * @param State $state
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        State $state,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->state = $state;
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * @return string
     */
    abstract protected function getAction(): string;

    /**
     * @inheirtDoc
     */
    public function render(AbstractElement $element): string
    {
        $attributes = [
            'label' => __($element->getLabel()),
            'onclick' => sprintf("location.href='%s'", $this->getUrl(static::getAction())),
            'id' => $element->getId(),
            'class' => 'apsis-resource-button',
        ];
        if ($this instanceof ResetButton && $this->state->getMode() === State::MODE_PRODUCTION) {
            $attributes['disabled'] = 'disabled';
        }
        return $this->_layout
            ->createBlock(Button::class)
            ->addData($attributes)
            ->toHtml();
    }
}
