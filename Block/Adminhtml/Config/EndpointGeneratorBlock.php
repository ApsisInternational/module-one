<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Apsis\One\Service\BaseService;

class EndpointGeneratorBlock extends Field
{
    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @param Context $context
     * @param BaseService $baseService
     * @param array $data
     */
    public function __construct(Context $context, BaseService $baseService, array $data = [])
    {
        parent::__construct($context, $data);
        $this->baseService = $baseService;
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $add = '';
        $acLink = '/v1/abandoned/%s/token/##com.apsis1.integrations.adobe-commerce.events.cart-abandoned-cart.token##';
        if ($element->getId() === 'apsis_one_connect_abandoned_cart_content_endpoint') {
            $add = sprintf($acLink, 'cart');
        } elseif ($element->getId() === 'apsis_one_connect_abandoned_cart_session_endpoint') {
            $add = sprintf($acLink, 'checkout');
        }

        $element->setData('value', $this->baseService->generateSystemAccessUrl($this->getRequest(), $add))
            ->setData('readonly', 1)
            ->addClass('apsis-copy-helper');
        return parent::_getElementHtml($element);
    }
}
