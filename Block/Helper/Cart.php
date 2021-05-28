<?php

namespace Apsis\One\Block\Helper;

use Apsis\One\Model\Service\Config;
use Apsis\One\Model\Service\Log;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Data\Form\FormKey;
use Exception;

class Cart extends Template
{
    const EMAIL_UPDATER_URL = 'apsis/cart/updater';

    /**
     * @var Log
     */
    private $logger;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * Cart constructor.
     *
     * @param Template\Context $context
     * @param FormKey $formKey
     * @param Log $logger
     * @param array $data
     */
    public function __construct(Template\Context $context, FormKey $formKey, Log $logger, array $data = [])
    {
        parent::__construct($context, $data);

        $this->formKey = $formKey;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getUpdaterUrl()
    {
        try {
            return $this->_storeManager
                ->getStore()
                ->getUrl(
                    self::EMAIL_UPDATER_URL,
                    ['_secure' => $this->_storeManager->getStore()->isCurrentlySecure()]
                );
        } catch (Exception $e) {
            $this->logger->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @return bool
     */
    public function isOkToProceed()
    {
        try {
            return (boolean) $this->_storeManager->getStore()->getConfig(
                Config::EVENTS_REGISTER_ABANDONED_CART_AFTER_DURATION
            );
        } catch (Exception $e) {
            $this->logger->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @return string
     */
    public function getKey()
    {
        try {
            return $this->formKey->getFormKey();
        } catch (Exception $e) {
            $this->logger->logError(__METHOD__, $e);
            return '';
        }
    }
}
