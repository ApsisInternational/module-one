<?php

namespace Apsis\One\Block\Adminhtml\Config\Profile;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SubscriptionEndpoint extends Field
{
    const SUBSCRIPTION_UPDATE_ENDPOINT = 'apsis/profile/subscription';

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Url constructor.
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
     * @param AbstractElement $element
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $text = sprintf(
            '%s%s',
            $this->apsisCoreHelper->generateBaseUrlForDynamicContent(),
            self::SUBSCRIPTION_UPDATE_ENDPOINT
        );
        $element->setData('value', $text);
        return parent::_getElementHtml($element);
    }
}
