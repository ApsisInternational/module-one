<?php

namespace Apsis\One\Plugin\Customer;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\UrlFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Newsletter\Controller\Manage\Index;
use Magento\Store\Model\ScopeInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Newsletter\Model\Subscriber;

class NewsletterManageIndexPlugin
{
    const APSIS_NEWSLETTER_MANAGE_URL = 'apsis/customer/index';

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Session
     */
    public $customerSession;

    /**
     * @var Http
     */
    private $response;

    /**
     * @var UrlFactory
     */
    private $urlFactory;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * NewsletterManageIndexPlugin constructor.
     *
     * @param Http $response
     * @param UrlFactory $urlFactory
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Session $customerSession
     * @param SubscriberFactory $subscriberFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     */
    public function __construct(
        Http $response,
        UrlFactory $urlFactory,
        ApsisCoreHelper $apsisCoreHelper,
        Session $customerSession,
        SubscriberFactory $subscriberFactory,
        ProfileCollectionFactory $profileCollectionFactory
    ) {
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->subscriberFactory = $subscriberFactory;
        $this->customerSession = $customerSession;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->response = $response;
        $this->urlFactory = $urlFactory;
    }

    /**
     * @param Index $subject
     * @param callable $proceed
     *
     * @return Http|HttpInterface
     */
    public function aroundExecute(
        Index $subject,
        callable $proceed
    ) {
        if ($this->isOkToProceed()) {
            return $this->response->setRedirect(
                $this->urlFactory->create()->getUrl(self::APSIS_NEWSLETTER_MANAGE_URL)
            );
        }
        return $proceed();
    }

    /**
     * @return bool
     */
    private function isOkToProceed()
    {
        $store = $this->customerSession->getCustomer()->getStore();
        $accountEnabled = $this->apsisCoreHelper->isEnabled(
            ScopeInterface::SCOPE_STORES,
            $this->customerSession->getCustomer()->getStoreId()
        );
        $selectedConsentTopics = (string) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
        );
        $syncEnabled = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED
        );
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberFactory->create()->loadByCustomerId(
            $this->customerSession->getCustomerId()
        );
        $profileFound = ($subscriber->getId() && $subscriber->isSubscribed()) ?
            $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getSubscriberId()) : false;
        return (
            $accountEnabled &&
            $syncEnabled &&
            strlen($selectedConsentTopics) &&
            $subscriber->getId() &&
            $profileFound
        );
    }
}