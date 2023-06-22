<?php

namespace Apsis\One\Service\Sub;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\QueueModel;
use Apsis\One\Model\ResourceModel\QueueResource;
use Apsis\One\Service\BaseService;
use Apsis\One\Service\QueueService;
use Apsis\One\Service\WebhookService;
use Apsis\One\Model\QueueModelFactory;
use Apsis\One\Model\ResourceModel\Queue\QueueCollectionFactory;
use Apsis\One\Model\ResourceModel\Queue\QueueCollection;
use Throwable;

class SubQueueService
{
    /**
     * @var QueueModelFactory
     */
    private QueueModelFactory $queueModelFactory;

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
     * @param QueueModelFactory $queueModelFactory
     * @param QueueResource $queueResource
     * @param WebhookService $webhookService
     * @param QueueCollectionFactory $queueCollectionFactory
     */
    public function __construct(
        QueueModelFactory $queueModelFactory,
        QueueResource $queueResource,
        WebhookService $webhookService,
        QueueCollectionFactory $queueCollectionFactory
    ) {
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->webhookService = $webhookService;
        $this->queueModelFactory = $queueModelFactory;
        $this->queueResource = $queueResource;
    }

    /**
     * @param ProfileModel $profile
     * @param BaseService $baseService
     * @param int $type
     * @param bool $validate
     *
     * @return void
     */
    public function registerItem(
        ProfileModel $profile,
        BaseService $baseService,
        int $type,
        bool $validate = true
    ): void {
        try {
            if (! isset(QueueService::QUEUE_TO_WEBHOOK_MAP[$type])) {
                return;
            }

            if ($validate) {
                $webhooks = $this->webhookService
                    ->getWebhookCollection()
                    ->getCollectionForStoreByType(
                        $profile->getStoreId(),
                        QueueService::QUEUE_TO_WEBHOOK_MAP[$type],
                        $this->webhookService
                    );
                if (is_int($webhooks) || ! $webhooks->getSize()) {
                    return;
                }
            }

            /** @var QueueModel|bool $queueItem */
            $queueItem = $this->getQueueCollection()
                ->findPendingQueueItemForStoreByType($profile->getStoreId(), $type, $profile->getId());
            if ($queueItem && in_array($type, [QueueModel::CONSENT_OPT_IN, QueueModel::CONSENT_OPT_OUT])) {
                /** @var QueueModel|bool $queueOppositeConsent */
                $queueOppositeConsent = $this->getQueueCollection()
                    ->findPendingQueueItemForStoreByType(
                        $profile->getStoreId(),
                        $type === QueueModel::CONSENT_OPT_IN ? QueueModel::CONSENT_OPT_OUT : QueueModel::CONSENT_OPT_IN,
                        $profile->getId()
                    );
                if ($queueOppositeConsent) {
                    $queueItem = false;
                }
            }

            if ($queueItem === false) {
                $queueItem = $this->getQueueModel()
                    ->setProfileId($profile->getId())
                    ->setStoreId($profile->getStoreId())
                    ->setType($type);
            }
            $this->queueResource->save($queueItem);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @return QueueCollection
     */
    private function getQueueCollection(): QueueCollection
    {
        return $this->queueCollectionFactory->create();
    }

    /**
     * @return QueueModel
     */
    private function getQueueModel(): QueueModel
    {
        return $this->queueModelFactory->create();
    }
}
