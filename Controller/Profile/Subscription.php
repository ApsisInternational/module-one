<?php

namespace Apsis\One\Controller\Profile;

use Apsis\One\Model\Profile as ProfileModel;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Escaper;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Subscription extends Action
{
    /**
     * @var ProfileCollectionFactory
     */
    private ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * @var SubscriberFactory
     */
    private SubscriberFactory $subscriberFactory;

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var Escaper
     */
    private Escaper $escaper;

    /**
     * Subscription constructor.
     *
     * @param Context $context
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param SubscriberFactory $subscriberFactory
     * @param ApsisCoreHelper $apsisLogHelper
     * @param Escaper $escaper
     */
    public function __construct(
        Context $context,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        SubscriberFactory $subscriberFactory,
        ApsisCoreHelper $apsisLogHelper,
        Escaper $escaper
    ) {
        $this->escaper = $escaper;
        $this->apsisCoreHelper = $apsisLogHelper;
        $this->subscriberFactory = $subscriberFactory;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->profileResource = $profileResource;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        try {
            //Validate http method against allowed one.
            if ('PATCH' !== $_SERVER['REQUEST_METHOD']) {
                return $this->sendResponse(405);
            }

            if (empty($key = (string) $this->getRequest()->getHeader('authorization')) ||
                ! $this->authenticateKey($key)
            ) {
                return $this->sendResponse(401);
            }

            $params = $this->getBodyParams();
            if (empty($params['PK']) || empty($params['TD'])) {
                return $this->sendResponse(400);
            }

            if (! $profile = $this->getProfile($params)) {
                return $this->sendResponse(404);
            }

            if ($profile->getSubscriberId() && $this->isTopicMatchedWithConfigTopic($profile, $params['TD'])) {
                $subscriber = $this->subscriberFactory->create()->load($profile->getSubscriberId());
                if ($subscriber->getId()) {
                    //Set subscriber status
                    $profile->setSubscriberStatus(Subscriber::STATUS_UNSUBSCRIBED)
                        ->setSubscriberStoreId($subscriber->getStoreId())
                        ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE)
                        ->setIsSubscriber(ProfileModel::NO_FLAG)
                        ->setErrorMessage('');
                    $this->profileResource->save($profile);

                    //Unsubscribe from Magento
                    $subscriber->unsubscribe();

                    //Log it
                    $info = [
                        'Request' => 'opt-out from JUSTIN',
                        'Profile Id' => $profile->getId(),
                        'Subscriber Id' => $profile->getSubscriberId(),
                        'Store Id' => $subscriber->getStoreId()
                    ];
                    $this->apsisCoreHelper->debug(__METHOD__, $info);

                    //Send success response
                    return $this->sendResponse(204);
                }
            }

            return $this->sendResponse(200);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return $this->sendResponse(500);
        }
    }

    /**
     * Fetch data from HTTP Request body.
     *
     * @return array
     */
    private function getBodyParams(): array
    {
        $bodyParams = [];
        if ($body = $this->getRequest()->getContent()) {
            $bodyParams = (array) $this->apsisCoreHelper->unserialize((string) $body);
        }
        return $bodyParams;
    }

    /**
     * @param ProfileModel $profile
     * @param string $topicDiscriminator
     *
     * @return bool
     */
    private function isTopicMatchedWithConfigTopic(ProfileModel $profile, string $topicDiscriminator): bool
    {
        $isSyncEnabled = (string) $this->apsisCoreHelper->getConfigValue(
            ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENABLED,
            ScopeInterface::SCOPE_STORES,
            ($profile->getSubscriberStoreId()) ? $profile->getSubscriberStoreId() : $profile->getStoreId()
        );
        if (! $isSyncEnabled) {
            return false;
        }

        $selectedTopicInConfig = (string) $this->apsisCoreHelper->getConfigValue(
            ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC,
            ScopeInterface::SCOPE_STORES,
            ($profile->getSubscriberStoreId()) ? $profile->getSubscriberStoreId() : $profile->getStoreId()
        );

        return strlen($selectedTopicInConfig) && ! empty($topicMappings = explode('|', $selectedTopicInConfig)) &&
            isset($topicMappings[0]) && $topicMappings[0] === $topicDiscriminator;
    }

    /**
     * @param int $code
     *
     * @return ResponseInterface
     */
    private function sendResponse(int $code): ResponseInterface
    {
        $this->getResponse()
            ->setHttpResponseCode($code)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true)
            ->setHeader('Content-Type', 'application/json', true);
        return $this->getResponse();
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    private function authenticateKey(string $key): bool
    {
        return $this->apsisCoreHelper->getSubscriptionEndpointKey() === $key;
    }

    /**
     * @param array $params
     *
     * @return ProfileModel|bool
     */
    private function getProfile(array $params): ProfileModel|bool
    {
        return $this->profileCollectionFactory->create()
            ->loadByIntegrationId($this->escaper->escapeHtml($params['PK']));
    }
}
