<?php

namespace Apsis\One\Block\Helper;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class Cart extends Template
{
    const EMAIL_UPDATER_URL = 'apsis/cart/updater';

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var StoreInterface
     */
    private $store;

    /**
     * Cart constructor.
     *
     * @param Template\Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $data
     *
     * @throws NoSuchEntityException
     */
    public function __construct(
        Template\Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        array $data = []
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context, $data);
        $this->store = $this->_storeManager->getStore();
    }

    /**
     * @return string
     */
    public function getUpdaterUrl()
    {
        return $this->store->getUrl(self::EMAIL_UPDATER_URL, ['_secure' => $this->store->isCurrentlySecure()]);
    }

    /**
     * @return bool
     */
    public function isOkToProceed()
    {
        $isEnabled = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $this->store->getId());
        $acDelayPeriod = $this->apsisCoreHelper->getStoreConfig(
            $this->store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_REGISTER_ABANDONED_CART_AFTER_DURATION
        );
        return ($isEnabled && $acDelayPeriod);
    }
}
