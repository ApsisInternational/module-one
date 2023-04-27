<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Controller\Api\Consents\Index as ConsentsIndex;
use Apsis\One\Model\Event;
use Apsis\One\Model\QueueFactory;
use Apsis\One\Model\ResourceModel\Queue as QueueResource;
use Apsis\One\Model\ResourceModel\Queue\Collection;
use Apsis\One\Model\ResourceModel\Queue\CollectionFactory as QueueCollectionFactory;
use Apsis\One\Model\Profile;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Webhook as WebhookService;
use Apsis\One\Model\Webhook as WebhookModel;
use Apsis\One\Model\Queue as QueueModel;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;
use CurlHandle;
use Throwable;

class Queue
{
    const QUEUE_TO_WEBHOOK_MAP = [
        QueueModel::RECORD_CREATED => WebhookModel::TYPE_RECORD,
        QueueModel::RECORD_UPDATED => WebhookModel::TYPE_RECORD,
        QueueModel::RECORD_DELETED => WebhookModel::TYPE_RECORD,
        QueueModel::CONSENT_OPT_IN => WebhookModel::TYPE_CONSENT,
        QueueModel::CONSENT_OPT_OUT => WebhookModel::TYPE_CONSENT
    ];
    const WEBHOOK_QUEUE_TYPES = [
        WebhookModel::TYPE_RECORD => [
            QueueModel::RECORD_CREATED => 'created',
            QueueModel::RECORD_UPDATED => 'updated',
            QueueModel::RECORD_DELETED => 'deleted'
        ],
        WebhookModel::TYPE_CONSENT => [
            QueueModel::CONSENT_OPT_IN => true,
            QueueModel::CONSENT_OPT_OUT => false
        ]
    ];
    const HTTP_CODE_TEXT = [
        204 => '',
        400 => 'Bad request',
        404 => 'Installation not found',
        413 => 'You sent more than 200 contacts',
        429 => 'Too many requests. Retry with exponential backoff',
        504 => 'Request timeout. Retry with exponential backoff',
        500 => 'Internal server (generic connector) error',
        0 => 'Unexpected HTTP code: '
    ];
    const EXPIRE_SECONDS = 172800; // 48 hours in seconds
    const INTERVAL_SECONDS = 300; // Rate set as 5 minutes

    /**
     * @var QueueFactory
     */
    private QueueFactory $queueFactory;

    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var WebhookService
     */
    private WebhookService $webhookService;

    /**
     * @var QueueCollectionFactory
     */
    private QueueCollectionFactory $queueCollectionFactory;

    /**
     * @var bool
     */
    private bool $isExpired;

    /**
     * @param QueueFactory $queueFactory
     * @param QueueResource $queueResource
     * @param Webhook $webhookService
     * @param QueueCollectionFactory $QueueCollectionFactory
     */
    public function __construct(
        QueueFactory $queueFactory,
        QueueResource $queueResource,
        WebhookService $webhookService,
        QueueCollectionFactory $QueueCollectionFactory
    ) {
        $this->queueCollectionFactory = $QueueCollectionFactory;
        $this->webhookService = $webhookService;
        $this->queueFactory = $queueFactory;
        $this->queueResource = $queueResource;
    }

    /**
     * @param Profile $profile
     * @param int $type
     * @param ApsisCoreHelper $coreHelper
     * @param bool $validate
     *
     * @return void
     */
    public function registerItem(Profile $profile, int $type, ApsisCoreHelper $coreHelper, bool $validate = true): void
    {
        try {
            if (! isset(self::QUEUE_TO_WEBHOOK_MAP[$type])) {
                return;
            }

            if ($validate) {
                $webhooks = $this->webhookService->getCollectionForStoreByType(
                    $profile->getStoreId(),
                    self::QUEUE_TO_WEBHOOK_MAP[$type],
                    $coreHelper
                );
                if (is_int($webhooks) || ! $webhooks->getSize()) {
                    return;
                }
            }

            $queue = $this->findQueueItem($profile->getStoreId(), $type, $profile->getId(), $coreHelper);
            if ($queue && in_array($type, [QueueModel::CONSENT_OPT_IN, QueueModel::CONSENT_OPT_OUT])) {
                $queueOppositeConsent = $this->findQueueItem(
                    $profile->getStoreId(),
                    $type === QueueModel::CONSENT_OPT_IN ? QueueModel::CONSENT_OPT_OUT : QueueModel::CONSENT_OPT_IN,
                    $profile->getId(),
                    $coreHelper
                );
                if ($queueOppositeConsent) {
                    unset($queue);
                }
            }

            if (! isset($queue)) {
                $queue = $this->queueFactory->create()
                    ->setProfileId($profile->getId())
                    ->setStoreId($profile->getStoreId())
                    ->setType($type);
            }
            $this->queueResource->save($queue);
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    public function processQueue(ApsisCoreHelper $apsisCoreHelper): void
    {
        foreach ($apsisCoreHelper->getStores() as $store) {
            try {
                foreach (array_keys(WebhookModel::TYPE_TEXT_MAP) as $type) {
                    $this->isExpired = false;
                    $this->processQueueByWebhookType($store, $type, $apsisCoreHelper);
                }
            } catch (Throwable $e) {
                $apsisCoreHelper->logError(__METHOD__, $e);
                $apsisCoreHelper->log(__METHOD__ . ' Skipped for store id: ' . $store->getId());
                continue;
            }
        }
    }

    /**
     * @param Store $store
     * @param int $type
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return void
     */
    private function processQueueByWebhookType(Store $store, int $type, ApsisCoreHelper $apsisCoreHelper): void
    {
        try {
            $webhooks = $this->webhookService->getWebhookForStoreByType($store->getId(), $type, $apsisCoreHelper, true);
            if (is_int($webhooks) || empty($webhooks)) {
                return;
            }

            $webhook = current($webhooks);

            /** @var WebhookModel $webhookObject */
            $webhookObject = $webhook['object'];
            if (! empty($webhookObject->getBackoffConfig())) {
                $status = $this->isOkToProceedWithRetry($webhookObject, $apsisCoreHelper);
                if (! $status) {
                    return;
                }
            }

            $collection = $this->getCollectionForStoreByWebhookType($store->getId(), $type, $apsisCoreHelper);
            if ($collection && $collection->getSize()) {
                $items = [];
                foreach ($collection as $item) {
                    $itemDataArr = $this->getItemDataArr($item, $type, $apsisCoreHelper);
                    if (empty($itemDataArr)) {
                        continue;
                    }
                    $items[$item->getId()] = $itemDataArr;
                }

                if (empty($items)) {
                    return;
                }

                $this->processWebhooks($webhookObject, $items, $apsisCoreHelper);
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            $apsisCoreHelper->log(__METHOD__ . ' Skipped for webhook type : ' . WebhookModel::TYPE_TEXT_MAP[$type]);
        }
    }

    /**
     * @param WebhookModel $webhook
     * @param Core $apsisCoreHelper
     *
     * @return bool
     */
    private function isOkToProceedWithRetry(WebhookModel $webhook, ApsisCoreHelper $apsisCoreHelper): bool
    {
        try {
            $status = false;
            $backoffConfig = json_decode($webhook->getBackoffConfig(), true);
            $retryCount = $backoffConfig['retryCount'] + 1;
            $nextRetryTimeStamp = $backoffConfig['lastTryTimeStamp'] + ($retryCount * self::INTERVAL_SECONDS);

            $currentTimeStamp = time();
            if ($currentTimeStamp >= $backoffConfig['firstTryTimeStamp'] + self::EXPIRE_SECONDS) {
                // Last retry.
                $this->isExpired = true;
                $status = true;
                $backoffConfig = [];
            } elseif ($currentTimeStamp >= $nextRetryTimeStamp) {
                // Next retry config.
                $backoffConfig['retryCount'] = $retryCount;
                $backoffConfig['lastTryTimeStamp'] = $currentTimeStamp;
                $status = true;
            } else {
                // Not enough time has passed, so no retry.
                unset($backoffConfig);
                $status = false;
            }

            if (isset($backoffConfig)) {
                $webhook->setBackoffConfig((string) json_encode($backoffConfig));
                $this->webhookService->webhookResource->save($webhook);
            }
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }

        return $status;
    }

    /**
     * @param WebhookModel $webhook
     * @param int $httpCode
     * @param Core $helper
     *
     * @return array
     */
    private function handleNonPermanentError(WebhookModel $webhook, int $httpCode, ApsisCoreHelper $helper): array
    {
        $status = Event::STATUS_PENDING;
        $message = '';

        try {
            if ($this->isExpired) {
                $backoffConfig = '';
                $status = QueueModel::STATUS_EXPIRED;
                $message = '48 hours has passed. ' . self::HTTP_CODE_TEXT[$httpCode];
            } elseif (empty($webhook->getBackoffConfig())) {
                // First try
                $currentTimeStamp = time();
                $backoffConfig = [
                    'firstTryTimeStamp' => $currentTimeStamp,
                    'retryCount' => 1,
                    'lastTryTimeStamp' => $currentTimeStamp
                ];
            }

            if (isset($backoffConfig)) {
                $webhook->setBackoffConfig(empty($backoffConfig) ? '' : (string) json_encode($backoffConfig));
                $this->webhookService->webhookResource->save($webhook);
            }
        } catch (Throwable $e) {
            $helper->logError(__METHOD__, $e);
            $status = Event::STATUS_PENDING;
            $message = '';
        }

        return ['status' => $status, 'message' => $message];
    }

    /**
     * @param WebhookModel $webhook
     * @param array $items
     * @param Core $apsisCoreHelper
     *
     * @return void
     */
    private function processWebhooks(WebhookModel $webhook, array $items, ApsisCoreHelper $apsisCoreHelper): void
    {
        try {
            $payload = (string) json_encode(array_values($items));
            $justinSignature = $this->getHmacHash($payload, $webhook->getSecret(), $apsisCoreHelper);
            if (empty($justinSignature)) {
                return;
            }

            $httpCode = $this->initiateAndExecuteCurlRequest(
                $payload,
                $justinSignature,
                $webhook->getCallbackUrl(),
                $apsisCoreHelper
            );
            if (empty($httpCode)) {
                return;
            }

            $this->updateQueue($webhook, array_keys($items), $httpCode, $apsisCoreHelper);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param WebhookModel $webhook
     * @param array $ids
     * @param int $httpCode
     * @param Core $coreHelper
     *
     * @return void
     */
    private function updateQueue(WebhookModel $webhook, array $ids, int $httpCode, ApsisCoreHelper $coreHelper): void
    {
        try {
            $bind = match ($httpCode) {
                204 => ['status' => Event::STATUS_SYNCED, 'message' => self::HTTP_CODE_TEXT[204]],
                429, 500, 504 => $this->handleNonPermanentError($webhook, $httpCode, $coreHelper),
                400, 404 => ['status' => Event::STATUS_FAILED, 'message' => self::HTTP_CODE_TEXT[$httpCode]],
                default => ['status' => Event::STATUS_FAILED, 'message' => self::HTTP_CODE_TEXT[0] . $httpCode],
            };
            $this->queueResource->updateQueue($ids, $bind, $coreHelper);
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $payload
     * @param string $justinSignature
     * @param string $url
     * @param Core $apsisCoreHelper
     *
     * @return int|null
     */
    private function initiateAndExecuteCurlRequest(
        string $payload,
        string $justinSignature,
        string $url,
        ApsisCoreHelper $apsisCoreHelper
    ) {
        try {
            /** @var CurlHandle $ch */
            $ch = curl_init();
            if (! $ch instanceof CurlHandle) {
                $apsisCoreHelper->log(__METHOD__ . 'Unable to initiate curl resource');
                return null;
            }

            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Justin-Signature: ' . $justinSignature
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // @todo Remove later on after testing
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // @todo Remove later on after testing
            curl_exec($ch);

            $curlError = curl_error($ch);
            if ($curlError) {
                $apsisCoreHelper->log(__METHOD__ . 'curl error: ' . $curlError);
                $httpCode = null;
            } else {
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            }

            curl_close($ch);
            return $httpCode;
        } catch (Throwable $e) {
            if (isset($ch)) {
                curl_close($ch);
            }
            $apsisCoreHelper->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param QueueModel $item
     * @param int $type
     * @param Core $apsisCoreHelper
     *
     * @return array
     */
    private function getItemDataArr(QueueModel $item, int $type, ApsisCoreHelper $apsisCoreHelper): array
    {
        try {
            $data = [
                "record_id" => $item->getProfileId(),
            ];
            if (WebhookModel::TYPE_RECORD === $type) {
                $data['operation'] = self::WEBHOOK_QUEUE_TYPES[$type][$item->getType()];
            } elseif (WebhookModel::TYPE_CONSENT === $type) {
                $data['consent_base_id'] = ConsentsIndex::CONSENT_BASE_ID;
                $data['has_consented'] = self::WEBHOOK_QUEUE_TYPES[$type][$item->getType()];
            }
            return $data;
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param string $payload
     * @param string $key
     * @param Core $apsisCoreHelper
     *
     * @return string|null
     */
    private function getHmacHash(string $payload, string $key, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            if (empty($payload) || empty($key)) {
                return null;
            }
            return hash_hmac('sha256', $payload, $key);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param int $storeId
     * @param int $type
     * @param Core $apsisCoreHelper
     *
     * @return Collection|null
     */
    private function getCollectionForStoreByWebhookType(int $storeId, int $type, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            return $this->queueCollectionFactory->create()
                ->addFieldToFilter('type', ['in' => array_keys(self::WEBHOOK_QUEUE_TYPES[$type])])
                ->addFieldToFilter('store_id', $storeId)
                ->addFieldToFilter('sync_status', Event::STATUS_PENDING)
                ->setPageSize(200);
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param int $storeId
     * @param int $type
     * @param int $profileId
     * @param Core $apsisCoreHelper
     *
     * @return DataObject|null
     */
    private function findQueueItem(int $storeId, int $type, int $profileId, ApsisCoreHelper $apsisCoreHelper)
    {
        try {
            $collection = $this->queueCollectionFactory->create()
                ->addFieldToFilter('type', $type)
                ->addFieldToFilter('store_id', $storeId)
                ->addFieldToFilter('profile_id', $profileId)
                ->addFieldToFilter('sync_status', Event::STATUS_PENDING)
                ->setPageSize(1);
            return $collection->getSize() ? $collection->getFirstItem() : null;
        } catch (Throwable $e) {
            $apsisCoreHelper->logError(__METHOD__, $e);
            return null;
        }
    }
}
