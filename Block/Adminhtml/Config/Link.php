<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Apsis\One\Model\Service\Log;
use Throwable;

class Link extends Field
{
    /**
     * @var Log
     */
    private Log $logger;

    /**
     * FieldBase constructor.
     *
     * @param Context $context
     * @param Log $logger
     * @param array $data
     */
    public function __construct(Context $context, Log $logger, array $data = [])
    {
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $text = sprintf(
            '%s%s',
            $this->generateBaseUrlForDynamicContent(),
            'apsis/sample/url'
        );
        $element->setData('value', $text)
            ->setData('readonly', 1)
            ->addClass('apsis-copy-helper');
        return parent::_getElementHtml($element);
    }

    /**
     * @return string
     */
    public function generateBaseUrlForDynamicContent(): string
    {
        try {
            $store =  $this->_storeManager->getStore($this->_request->getParam('store'));
            return $store->getBaseUrl() . $store->getCode() . '/';
        } catch (Throwable $e) {
            $this->logger->logError(__METHOD__, $e);
            return '';
        }
    }
}
