<?php

namespace Apsis\One\Plugin\Customer;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\UrlFactory;
use Magento\Newsletter\Controller\Manage\Index;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;

class NewsletterManageIndexPlugin
{
    const APSIS_NEWSLETTER_MANAGE_URL = 'apsis/customer/index';

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var Session
     */
    public Session $customerSession;

    /**
     * @var Http
     */
    private Http $response;

    /**
     * @var UrlFactory
     */
    private UrlFactory $urlFactory;

    /**
     * @var SubscriberFactory
     */
    private SubscriberFactory $subscriberFactory;

    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

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
    public function aroundExecute(Index $subject, callable $proceed): HttpInterface|Http
    {
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
    private function isOkToProceed(): bool
    {
        $store = $this->customerSession->getCustomer()->getStore();
        $accountEnabled = $this->apsisCoreHelper->isEnabled(
            ScopeInterface::SCOPE_STORES,
            $this->customerSession->getCustomer()->getStoreId()
        );
        $selectedConsentTopics = (string) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC
        );
        $syncEnabled = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENABLED
        );
        $subscriber = $this->subscriberFactory->create()->loadByCustomerId(
            $this->customerSession->getCustomerId()
        );

        $profileFound = ($subscriber->getId() && $subscriber->isSubscribed()) ?
            $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getSubscriberId()) : false;

        $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::MAPPINGS_SECTION_SECTION
        );

        return (
            $accountEnabled &&
            $syncEnabled &&
            strlen($selectedConsentTopics) &&
            $subscriber->getId() &&
            $profileFound &&
            $profileFound->getSubscriberSyncStatus() &&
            $sectionDiscriminator
        );
    }
}
