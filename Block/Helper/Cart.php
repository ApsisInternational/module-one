<?php

namespace Apsis\One\Block\Helper;

use Apsis\One\Model\Service\Config;
use Apsis\One\Model\Service\Log;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Store\Api\Data\StoreInterface;
use Exception;

class Cart extends Template
{
    const EMAIL_UPDATER_URL = 'apsis/cart/updater';

    /**
     * @var StoreInterface
     */
    private $store;

    /**
     * @var Log
     */
    private $logger;

    /**
     * Cart constructor.
     *
     * @param Template\Context $context
     * @param Log $logger
     * @param array $data
     *
     * @throws NoSuchEntityException
     */
    public function __construct(Template\Context $context, Log $logger, array $data = [])
    {
        parent::__construct($context, $data);

        $this->logger = $logger;
        $this->store = $this->_storeManager->getStore();
    }

    /**
     * @return string
     */
    public function getUpdaterUrl()
    {
        try {
            return $this->store->getUrl(self::EMAIL_UPDATER_URL, ['_secure' => $this->store->isCurrentlySecure()]);
        } catch (Exception $e) {
            $this->logger->logError(__METHOD__, $e);
        }
        return '';
    }

    /**
     * @return bool
     */
    public function isOkToProceed()
    {
        $isEnabled = (boolean) $this->store->getConfig(Config::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED);
        $acDelayPeriod = (int) $this->store->getConfig(
            Config::CONFIG_APSIS_ONE_EVENTS_REGISTER_ABANDONED_CART_AFTER_DURATION
        );

        return ($isEnabled && $acDelayPeriod);
    }
}
