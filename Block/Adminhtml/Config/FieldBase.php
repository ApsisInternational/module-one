<?php

namespace Apsis\One\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Apsis\One\Model\Service\Log;
use Throwable;

class FieldBase extends Field
{
    /**
     * @var Log
     */
    private $logger;

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
        $element->setData('readonly', 1)
            ->addClass('apsis-copy-helper');
        return parent::_getElementHtml($element);
    }

    /**
     * @return string
     */
    public function generateBaseUrlForDynamicContent()
    {
        try {
            $website = $this->_storeManager->getWebsite($this->_request->getParam('website', 0));
            $defaultGroup = $website->getDefaultGroup();
            $store =  (! $defaultGroup) ? null : $defaultGroup->getDefaultStore();
            return $this->_storeManager->getStore($store)->getBaseUrl();
        } catch (Throwable $e) {
            $this->logger->logError(__METHOD__, $e);
            return '';
        }
    }
}
