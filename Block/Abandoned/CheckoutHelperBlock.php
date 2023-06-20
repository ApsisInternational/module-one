<?php

namespace Apsis\One\Block\Abandoned;

use Apsis\One\Service\BaseService;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Throwable;

class CheckoutHelperBlock extends Template
{
    const UPDATER_URL = 'apsis/abandoned/helper';

    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var FormKey
     */
    private FormKey $formKey;

    /**
     * @param Context $context
     * @param FormKey $formKey
     * @param BaseService $baseService
     * @param array $data
     */
    public function __construct(Context $context, FormKey $formKey, BaseService $baseService, array $data = [])
    {
        parent::__construct($context, $data);
        $this->formKey = $formKey;
        $this->baseService = $baseService;
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
                    self::UPDATER_URL,
                    ['_secure' => $this->_storeManager->getStore()->isCurrentlySecure()]
                );
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
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
            $this->baseService->logError(__METHOD__, $e);
            return '';
        }
    }
}
