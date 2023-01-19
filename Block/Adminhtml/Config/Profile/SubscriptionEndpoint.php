<?php

namespace Apsis\One\Block\Adminhtml\Config\Profile;

use Apsis\One\Block\Adminhtml\Config\FieldBase;
use Apsis\One\Model\Service\Log;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SubscriptionEndpoint extends FieldBase
{
    const SUBSCRIPTION_UPDATE_ENDPOINT = 'apsis/profile/subscription';

    /**
     * SubscriptionEndpoint constructor.
     *
     * @param Context $context
     * @param Log $logger
     * @param array $data
     */
    public function __construct(Context $context, Log $logger, array $data = [])
    {
        parent::__construct($context, $logger, $data);
    }

    /**
     * @inheritdoc
     */
    public function _getElementHtml(AbstractElement $element): string
    {
        $text = sprintf(
            '%s%s',
            $this->generateBaseUrlForDynamicContent(),
            self::SUBSCRIPTION_UPDATE_ENDPOINT
        );
        $element->setData('value', $text);
        return parent::_getElementHtml($element);
    }
}
