<?php

namespace Apsis\One\Block\Abandoned;

use Apsis\One\Model\Service\Log;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Data\Form\FormKey;
use Throwable;

class Helper extends Template
{
    const EMAIL_UPDATER_URL = 'apsis/abandoned/helper';

    /**
     * @var Log
     */
    private Log $logger;

    /**
     * @var FormKey
     */
    private FormKey $formKey;

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
    public function getUpdaterUrl(): string
    {
        try {
            return $this->_storeManager
                ->getStore()
                ->getUrl(
                    self::EMAIL_UPDATER_URL,
                    ['_secure' => $this->_storeManager->getStore()->isCurrentlySecure()]
                );
        } catch (Throwable $e) {
            $this->logger->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        try {
            return $this->formKey->getFormKey();
        } catch (Throwable $e) {
            $this->logger->logError(__METHOD__, $e);
            return '';
        }
    }
}
