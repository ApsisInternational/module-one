<?php

namespace Apsis\One\Controller\Profile;

use Apsis\One\Model\Profile;
use Apsis\One\Model\Profile as ProfileModel;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Escaper;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;

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
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            if (empty($key = (string) $this->getRequest()->getHeader('authorization')) ||
                ! $this->authenticateKey($key)
            ) {
                return $this->sendResponse(401);
            }

            $params = $this->getRequest()->getParams();
            if (empty($params['KS_ID'])) {
                return $this->sendResponse(400);
            }

            if (! $profile = $this->validateId($params)) {
                return $this->sendResponse(404);
            }

            if ($profile->getSubscriberId()) {
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
                    return $this->sendResponse(204);
                }
            }

            return $this->sendResponse(200, 'No change made to profile subscription.');
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return $this->sendResponse(500, $e->getMessage());
        }
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
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', 'text/html; charset=UTF-8', true);
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
     * @return Profile|bool
     */
    private function validateId(array $params)
    {
        return $this->profileCollectionFactory->create()
            ->loadByIntegrationId($this->escaper->escapeHtml($params['KS_ID']));
    }
}
