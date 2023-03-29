<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\QueueFactory;
use Apsis\One\Model\ResourceModel\Queue as QueueResource;
use Apsis\One\Model\Profile;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Webhook as WebhookService;
use Apsis\One\Model\Webhook as WebhookModel;
use Apsis\One\Model\Queue as QueueModel;
use Throwable;

class Queue
{
    const QUEUE_TO_WEBHOOK_MAP = [
        QueueModel::TYPE_RECORD_CREATED => WebhookModel::TYPE_RECORD,
        QueueModel::TYPE_RECORD_UPDATED => WebhookModel::TYPE_RECORD,
        QueueModel::TYPE_RECORD_DELETED => WebhookModel::TYPE_RECORD,
        QueueModel::TYPE_CONSENT_OPT_IN => WebhookModel::TYPE_CONSENT,
        QueueModel::TYPE_CONSENT_OPT_OUT => WebhookModel::TYPE_CONSENT
    ];

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
     * @param QueueFactory $queueFactory
     * @param QueueResource $queueResource
     * @param Webhook $webhookService
     */
    public function __construct(
        QueueFactory $queueFactory,
        QueueResource $queueResource,
        WebhookService $webhookService
    ) {
        $this->webhookService = $webhookService;
        $this->queueFactory = $queueFactory;
        $this->queueResource = $queueResource;
    }

    /**
     * @param Profile $profile
     * @param int $type
     * @param ApsisCoreHelper $coreHelper
     *
     * @return void
     */
    public function registerItem(Profile $profile, int $type, ApsisCoreHelper $coreHelper): void
    {
        try {
            if (! isset(self::QUEUE_TO_WEBHOOK_MAP[$type])) {
                return;
            }

            $webhookType = self::QUEUE_TO_WEBHOOK_MAP[$type];
            $webhooks = $this->webhookService
                ->getAllWebhooksForStoreByType($profile->getStoreId(), $webhookType, $coreHelper);
            if (empty($webhooks) || $webhooks === 500) {
                return;
            }

            if ($webhookType === WebhookModel::TYPE_CONSENT || $type === QueueModel::TYPE_RECORD_CREATED) {
                $this->addQueueItem($profile, $type, $coreHelper);
            }

            if ($type === QueueModel::TYPE_RECORD_UPDATED) {
                // @todo Only send record updates for mapped fields that were modified
                $this->addQueueItem($profile, $type, $coreHelper);
            }
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Profile $profile
     * @param int $type
     * @param Core $coreHelper
     */
    private function addQueueItem(Profile $profile, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $queue = $this->queueFactory->create()
                ->setProfileId($profile->getId())
                ->setStoreId($profile->getStoreId())
                ->setType($type);
            $this->queueResource->save($queue);
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
        }
    }
}
