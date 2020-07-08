<?php

namespace Apsis\One\Block\Adminhtml\Config\Abandoned;

use Magento\Config\Block\System\Config\Form\Field;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;
use Exception;
use Magento\Store\Model\StoreManagerInterface;

class Url extends Field
{
    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Url constructor.
     *
     * @param Context $context
     * @param ApsisLogHelper $apsisLogHelper
     * @param StoreManagerInterface $storeManager,
     * @param array $data
     */
    public function __construct(
        Context $context,
        ApsisLogHelper $apsisLogHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->apsisLogHelper = $apsisLogHelper;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $text = sprintf(
            '%sapsis/abandoned/cart/token/TOKEN/output/OUTPUT_TYPE/limit/NUMBER_LIMIT',
            $this->generateBaseUrlForDynamicContent()
        );
        $element->setData('value', $text);
        return parent::_getElementHtml($element);
    }

    /**
     * @return string
     */
    private function generateBaseUrlForDynamicContent()
    {
        try {
            $website = $this->storeManager->getWebsite($this->_request->getParam('website', 0));
            $defaultGroup = $website->getDefaultGroup();
            $store =  (! $defaultGroup) ? null : $defaultGroup->getDefaultStore();
            return $this->storeManager->getStore($store)->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return '';
        }
    }
}
