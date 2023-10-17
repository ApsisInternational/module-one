<?php

namespace Apsis\One\Service\Sub;

use Apsis\One\Model\ResourceModel\Webhook\WebhookCollection;
use Apsis\One\Model\WebhookModel;
use Apsis\One\Service\BaseService;
use Throwable;

class SubWebhookService
{
    /**
     * @param int $storeId
     * @param int $type
     * @param BaseService $baseService
     * @param WebhookCollection $webhookCollection
     * @param bool $object
     *
     * @return array|int
     */
    public function getWebhookForStoreByType(
        int $storeId,
        int $type,
        BaseService $baseService,
        WebhookCollection $webhookCollection,
        bool $object = false
    ): int|array {
        try {
            $collection = $webhookCollection->getCollectionForStoreByType($storeId, $type, $baseService);
            if (is_int($collection)) {
                return $collection;
            }

            if (! $collection->getSize()) {
                return [];
            }

            $dynIndex = [WebhookModel::TYPE_RECORD => 'fields', WebhookModel::TYPE_CONSENT => 'consent_base_ids'];
            $webhooks = [];
            /** @var WebhookModel $item */
            foreach ($collection as $item) {
                if ($object) {
                    $webhook['object'] = $item;
                } else {
                    $webhook = [
                        'subscription_id' => $item->getSubscriptionId(),
                        'callback_url' => $item->getCallbackUrl(),
                        'entity' => 'profile',
                        $dynIndex[$type] => explode(',', $item->getFields())
                    ];
                }
                $webhooks[] = $webhook;
            }
            return $webhooks;
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return 500;
        }
    }
}
