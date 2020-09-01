<?php

namespace Apsis\One\Controller\Profile;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Magento\Framework\App\ResponseInterface;
use Exception;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Controller\ResultInterface;
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
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * Subscription constructor.
     *
     * @param Context $context
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param SubscriberFactory $subscriberFactory
     * @param ApsisLogHelper $apsisLogHelper
     */
    public function __construct(
        Context $context,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        SubscriberFactory $subscriberFactory,
        ApsisLogHelper $apsisLogHelper
    ) {
        $this->apsisLogHelper = $apsisLogHelper;
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
            $params = $this->getRequest()->getParams();
            if (! empty($params['KS_ID']) && $this->isClean($params['KS_ID']) &&
                ! empty($profile = $this->profileCollectionFactory->create()->loadByIntegrationId($params['KS_ID'])) &&
                ! empty($params['EMAIL']) && $params['EMAIL'] === $profile->getEmail() && ! empty($params['CLD'])
            ) {
                if ($params['CLD'] === 'all') {
                    $subscriber = $this->subscriberFactory->create()
                        ->setStoreId($profile->getStoreId())
                        ->loadByEmail($params['EMAIL']);
                    if ($subscriber->getId()) {
                        $subscriber->unsubscribe();
                    }
                } elseif (! empty($params['TD']) &&
                    ! empty($profileConsent = explode(',', $profile->getTopicSubscription()))
                ) {
                    $consent = $this->processConsent($profileConsent, $params['TD']);
                    $profile->setTopicSubscription(empty($consent) ? '-' : $consent)
                        ->setSubscriberSyncStatus(Profile::SYNC_STATUS_PENDING);
                    $this->profileResource->save($profile);
                } else {
                    return $this->sendResponse(401, '401 Unauthorized');
                }
            } else {
                return $this->sendResponse(401, '401 Unauthorized');
            }
            return $this->sendResponse(204);
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
     * @param string $string
     *
     * @return bool
     */
    private function isClean(string $string)
    {
        return ! preg_match("/[^a-zA-Z\d-]/i", $string);
    }

    /**
     * @param array $profileConsent
     * @param string $TD
     *
     * @return string
     */
    private function processConsent(array $profileConsent, string $TD)
    {
        try {
            $original = $profileConsent;
            foreach ($profileConsent as $index => $value) {
                if (! empty($consent = explode('|', $value)) && is_array($consent) && count($consent) === 4 &&
                    $TD === $consent[1]) {
                    unset($profileConsent[$index]);
                }
            }
            return empty($profileConsent) ? '' : implode(',', $profileConsent);
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return implode(',', $original);
        }
    }
}
