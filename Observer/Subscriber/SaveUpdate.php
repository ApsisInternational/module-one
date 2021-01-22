<?php

namespace Apsis\One\Observer\Subscriber;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Exception;
use Magento\Store\Model\ScopeInterface;

class SaveUpdate implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var Profile
     */
    private $profileService;

    /**
     * SaveUpdate constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param Profile $profileService
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        Profile $profileService
    ) {
        $this->profileService = $profileService;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Subscriber $subscriber */
            $subscriber = $observer->getEvent()->getSubscriber();
            $store = $this->apsisCoreHelper->getStore($subscriber->getStoreId());
            $account = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getStoreId());

            if ($account) {
                if (! $store->getWebSite()) {
                    return $this;
                }
                $storeIds = $store->getWebSite()->getStoreIds();
                $found = $this->profileCollectionFactory->create()->loadBySubscriberId($subscriber->getId());
                $profile = ($found) ? $found : $this->profileCollectionFactory->create()->loadByEmailAndStoreId(
                    $subscriber->getEmail(),
                    $storeIds
                );

                if (! $profile) {
                    $this->profileService->createProfileForSubscriber($subscriber);
                } else {
                    $this->profileService->updateProfileForSubscriber($subscriber, $profile, $store);
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }

        return $this;
    }
}
