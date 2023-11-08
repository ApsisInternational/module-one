<?php

namespace Apsis\One\Service;

use Apsis\One\Controller\Api\Consents\Index as ConsentsIndex;
use Apsis\One\Logger\Logger;
use Apsis\One\Model\EventModel;
use Apsis\One\Model\ResourceModel\QueueResource;
use Apsis\One\Model\ResourceModel\Queue\QueueCollection;
use Apsis\One\Model\ResourceModel\Queue\QueueCollectionFactory;
use Apsis\One\Model\ResourceModel\WebhookResource;
use Apsis\One\Service\Sub\SubWebhookService;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronCollectionFactory;
use Apsis\One\Model\ResourceModel\Webhook\WebhookCollection;
use Apsis\One\Model\ResourceModel\Webhook\WebhookCollectionFactory;
use Apsis\One\Model\WebhookModel;
use Apsis\One\Model\QueueModel;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;
use CurlHandle;

class QueueService extends AbstractCronService
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
        0 => 'Unexpected HTTP code'
    ];
    const EXPIRE_SECONDS = 172800; // 48 hours in seconds
    const INTERVAL_SECONDS = 300; // Rate set as 5 minutes

    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var SubWebhookService
     */
    private SubWebhookService $subWebhookService;

    /**
     * @var WebhookCollectionFactory
     */
    private WebhookCollectionFactory $webhookCollectionFactory;

    /**
     * @var QueueCollectionFactory
     */
    private QueueCollectionFactory $queueCollectionFactory;

    /**
     * @var WebhookResource
     */
    private WebhookResource $webhookResource;

    /**
     * @var bool
     */
    private bool $isExpired;

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param CronCollectionFactory $cronCollectionFactory
     * @param ModuleListInterface $moduleList
     * @param QueueResource $queueResource
     * @param QueueCollectionFactory $queueCollectionFactory
     * @param WebhookCollectionFactory $webhookCollectionFactory
     * @param WebhookResource $webhookResource
     * @param SubWebhookService $subWebhookService
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        CronCollectionFactory $cronCollectionFactory,
        ModuleListInterface $moduleList,
        QueueResource $queueResource,
        QueueCollectionFactory $queueCollectionFactory,
        WebhookCollectionFactory $webhookCollectionFactory,
        WebhookResource $webhookResource,
        SubWebhookService $subWebhookService
    ) {
        parent::__construct($logger, $storeManager, $writer, $cronCollectionFactory, $moduleList);
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->queueResource = $queueResource;
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->webhookResource = $webhookResource;
        $this->subWebhookService = $subWebhookService;
    }

    /**
     * @inheritDoc
     */
    protected function getEntityCronJobCode(): string
    {
        return 'apsis_one_process_queue';
    }

    /**
     * @return WebhookCollection
     */
    private function getWebhookCollection(): WebhookCollection
    {
        return $this->webhookCollectionFactory->create();
    }

    /**
     * @inheritDoc
     */
    protected function runEntityCronjobTaskByStore(StoreInterface $store): void
    {
        try {
            foreach (array_keys(WebhookModel::TYPE_TEXT_MAP) as $type) {
                $this->isExpired = false;
                $this->processQueueByWebhookType($store, $type);
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return;
        }
    }

    /**
     * @param StoreInterface $store
     * @param int $type
     *
     * @return void
     */
    private function processQueueByWebhookType(StoreInterface $store, int $type): void
    {
        try {
            $webhooks = $this->subWebhookService
                ->getWebhookForStoreByType($store->getId(), $type, $this, $this->getWebhookCollection(), true);
            if (is_int($webhooks) || empty($webhooks)) {
                return;
            }

            $webhook = current($webhooks);

            /** @var WebhookModel $webhookObject */
            $webhookObject = $webhook['object'];
            if (! empty($webhookObject->getBackoffConfig())) {
                $status = $this->isOkToProceedWithRetry($webhookObject);
                if (! $status) {
                    return;
                }
            }

            $collection = $this->getQueueCollection()
                ->getCollectionForStoreByWebhookType($store->getId(), array_keys(self::WEBHOOK_QUEUE_TYPES[$type]));
            if ($collection->getSize()) {
                $items = [];
                foreach ($collection as $item) {
                    $itemDataArr = $this->getItemDataArr($item, $type);
                    if (empty($itemDataArr)) {
                        continue;
                    }
                    $items[$item->getId()] = $itemDataArr;
                }

                if (empty($items)) {
                    return;
                }

                $this->processWebhooks($webhookObject, $items);
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param WebhookModel $webhook
     *
     * @return bool
     */
    private function isOkToProceedWithRetry(WebhookModel $webhook): bool
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
            }

            if (isset($backoffConfig)) {
                $webhook->setBackoffConfig(empty($backoffConfig) ? '' : (string) json_encode($backoffConfig));
                $this->webhookResource->save($webhook);
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }

        return $status;
    }

    /**
     * @param WebhookModel $webhook
     * @param int $httpCode
     *
     * @return array
     */
    private function handleNonPermanentError(WebhookModel $webhook, int $httpCode): array
    {
        $status = EventModel::STATUS_PENDING;
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
                $this->webhookResource->save($webhook);
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            $status = EventModel::STATUS_PENDING;
            $message = '';
        }

        return ['sync_status' => $status, 'error_message' => $message];
    }

    /**
     * @param WebhookModel $webhook
     * @param array $items
     *
     * @return void
     */
    private function processWebhooks(WebhookModel $webhook, array $items): void
    {
        try {
            $payload = (string) json_encode(array_values($items));
            $justinSignature = $this->getHmacHash($payload, $webhook->getSecret());
            if (empty($justinSignature)) {
                return;
            }

            $httpCode = $this->initiateAndExecuteCurlRequest(
                $payload,
                $justinSignature,
                $webhook->getCallbackUrl()
            );
            if (empty($httpCode)) {
                return;
            }

            $this->updateQueue($webhook, array_keys($items), $httpCode);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param WebhookModel $webhook
     * @param array $ids
     * @param int $httpCode
     *
     * @return void
     */
    private function updateQueue(WebhookModel $webhook, array $ids, int $httpCode): void
    {
        try {
            $bind = match ($httpCode) {
                204 => [
                    'sync_status' => EventModel::STATUS_SYNCED,
                    'error_message' => self::HTTP_CODE_TEXT[204]
                ],
                429, 500, 504 => $this->handleNonPermanentError($webhook, $httpCode),
                400, 404 => [
                    'sync_status' => EventModel::STATUS_FAILED,
                    'error_message' => $httpCode . ' : '. self::HTTP_CODE_TEXT[$httpCode]
                ],
                default => [
                    'sync_status' => EventModel::STATUS_FAILED,
                    'error_message' => $httpCode . ' : '. self::HTTP_CODE_TEXT[0]
                ],
            };
            if ($bind['sync_status'] === EventModel::STATUS_FAILED ||
                $bind['sync_status'] === EventModel::STATUS_SYNCED) {
                $webhook->setBackoffConfig('');
                $this->webhookResource->save($webhook);
            }
            $this->queueResource->updateItemsByIds($ids, $bind, $this);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param string $payload
     * @param string $justinSignature
     * @param string $url
     *
     * @return int|null
     */
    private function initiateAndExecuteCurlRequest(string $payload, string $justinSignature, string $url): ?int
    {
        try {
            /** @var CurlHandle $ch */
            $ch = curl_init();
            if (! $ch instanceof CurlHandle) {
                $this->log(__METHOD__ . 'Unable to initiate curl resource');
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
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_exec($ch);

            $curlError = curl_error($ch);
            if ($curlError) {
                $this->log(__METHOD__ . 'curl error: ' . $curlError);
                $httpCode = null;
            } else {
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (getenv('APSIS_DEVELOPER')) {
                    $info = [
                        'Method' => __METHOD__,
                        'curl_request_info' => curl_getinfo($ch),
                        'curl_headers' => $headers,
                        'curl post payload' => json_decode($payload, true)
                    ];
                    $this->debug('CURL Transfer', $info);
                }
            }

            curl_close($ch);
            return $httpCode;
        } catch (Throwable $e) {
            if (isset($ch)) {
                curl_close($ch);
            }
            $this->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param QueueModel $item
     * @param int $type
     *
     * @return array
     */
    private function getItemDataArr(QueueModel $item, int $type): array
    {
        try {
            $data = [
                'record_id' => $item->getProfileId(),
            ];
            if (WebhookModel::TYPE_RECORD === $type) {
                $data['operation'] = self::WEBHOOK_QUEUE_TYPES[$type][$item->getType()];
            } elseif (WebhookModel::TYPE_CONSENT === $type) {
                $data['consent_base_id'] = ConsentsIndex::CONSENT_BASE_ID;
                $data['has_consented'] = self::WEBHOOK_QUEUE_TYPES[$type][$item->getType()];
            }
            return $data;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param string $payload
     * @param string $key
     *
     * @return string|null
     */
    private function getHmacHash(string $payload, string $key): ?string
    {
        try {
            if (empty($payload) || empty($key)) {
                return null;
            }
            return 'sha256=' . hash_hmac('sha256', $payload, $key);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @return QueueCollection
     */
    private function getQueueCollection(): QueueCollection
    {
        return $this->queueCollectionFactory->create();
    }
}
