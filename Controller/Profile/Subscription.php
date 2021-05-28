<?php

namespace Apsis\One\Controller\Profile;

use Apsis\One\Model\Profile as ProfileModel;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Escaper;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;

class Subscription extends Action
{
    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Escaper
     */
    private $escaper;

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
     * @inheritdoc
     */
    public function execute()
    {
        try {
            //Validate http method against allowed one.
            if ('PATCH' !== $_SERVER['REQUEST_METHOD']) {
                $msg = $_SERVER['REQUEST_METHOD'] . ': method not allowed to this endpoint.';
                return $this->sendResponse( 405, json_encode(['httpCode' => 405, 'message' => $msg]));
            }

            if (empty($key = (string) $this->getRequest()->getHeader('authorization')) ||
                ! $this->authenticateKey($key)
            ) {
                return $this->sendResponse(401);
            }

            $params = $this->getBodyParams();
            if (empty($params['PK']) || empty($params['TD']) || empty($params['CLD'])) {
                return $this->sendResponse(400);
            }

            if (! $profile = $this->validateId($params)) {
                return $this->sendResponse(404);
            }

            if ($profile->getSubscriberId() && $this->isTopicMatchedWithConfigTopic($profile, $params)) {
                $subscriber = $this->subscriberFactory->create()->load($profile->getSubscriberId());
                if ($subscriber->getId()) {

                    //Set subscriber status
                    $profile->setSubscriberStatus(Subscriber::STATUS_UNSUBSCRIBED)
                        ->setSubscriberStoreId($subscriber->getStoreId())
                        ->setSubscriberSyncStatus(ProfileModel::SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE)
                        ->setIsSubscriber(ProfileModel::NO_FLAGGED)
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

            return $this->sendResponse(200, 'No change made to profile subscription.');
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return $this->sendResponse(500, $e->getMessage());
        }
    }

    /**
     * Fetch data from HTTP Request body.
     *
     * @return array
     */
    private function getBodyParams()
    {
        $bodyParams = [];
        if ($body = $this->getRequest()->getContent()) {
            $bodyParams = (array) $this->apsisCoreHelper->unserialize((string) $body);
        }
        return $bodyParams;
    }

    /**
     * @param ProfileModel $profile
     * @param array $params
     *
     * @return bool
     */
    private function isTopicMatchedWithConfigTopic(ProfileModel $profile, array $params)
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

        if (strlen($selectedTopicInConfig) &&
            ! empty($topicMappings = explode('|', $selectedTopicInConfig)) &&
            isset($topicMappings[0]) && isset($topicMappings[1])
        ) {
            return ($topicMappings[0] === $params['CLD'] && $topicMappings[1] === $params['TD']);
        }

        return false;
    }

    /**
     * @param int $code
     * @param string $body
     *
     * @return ResponseInterface
     */
    private function sendResponse(int $code, string $body = '')
    {
        $this->getResponse()
            ->setHttpResponseCode($code)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true)
            ->setHeader('Content-Type', 'application/json', true);
        if (strlen($body)) {
            $this->getResponse()->setBody($body);
        }
        return $this->getResponse();
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    private function authenticateKey(string $key)
    {
        return $this->apsisCoreHelper->getSubscriptionEndpointKey() === $key;
    }

    /**
     * @param array $params
     *
     * @return ProfileModel|bool
     */
    private function validateId(array $params)
    {
        return $this->profileCollectionFactory->create()
            ->loadByIntegrationId($this->escaper->escapeHtml($params['PK']));
    }
}
