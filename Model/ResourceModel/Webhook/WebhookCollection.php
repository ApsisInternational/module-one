<?php

namespace Apsis\One\Model\ResourceModel\Webhook;

use Apsis\One\Model\ResourceModel\AbstractCollection;
use Apsis\One\Model\WebhookModel;
use Apsis\One\Model\ResourceModel\WebhookResource;
use Apsis\One\Service\BaseService;
use Throwable;

class WebhookCollection extends AbstractCollection
{
    const MODEL = WebhookModel::class;
    const RESOURCE_MODEL = WebhookResource::class;

    /**
     * @param string $subscriptionId
     * @param int $type
     * @param BaseService $service
     * @param int $storeId
     *
     * @return WebhookModel|int
     */
    public function getWebhookObject(
        string $subscriptionId,
        int $type,
        BaseService $service,
        int $storeId = 0
    ): WebhookModel|int {
        try {
            $filters = ['type' => $type];
            if (strlen($subscriptionId)) {
                $filters['subscription_id'] = $subscriptionId;
            }
            if ($storeId > 0) {
                $filters['store_id'] = $storeId;
            }

            $collection = $this->getCollection($filters, null, 1);
            if ($collection->getSize()) {
                /** @var WebhookModel $item */
                $item = $collection->getFirstItem();
            } else {
                $item = 404;
            }

            return $item;
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param string $callbackUrl
     * @param int $type
     * @param BaseService $service
     *
     * @return string|int|bool
     */
    public function findWebhookByCallbackUrlForType(
        string $callbackUrl,
        int $type,
        BaseService $service
    ): bool|int|string {
        try {
            $collection = $this->getCollection(['type' => $type, 'callback_url' => $callbackUrl], null, 1);
            if ($collection->getSize()) {
                /** @var WebhookModel $item */
                $item = $collection->getFirstItem();
                return (string) $item->getSubscriptionId();
            } else {
                return false;
            }
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param int $storeId
     * @param int $type
     * @param BaseService $service
     *
     * @return WebhookCollection|int
     */
    public function getCollectionForStoreByType(int $storeId, int $type, BaseService $service): WebhookCollection|int
    {
        try {
            return $this->getCollection(['type' => $type, 'store_id' => $storeId], null, 1);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 500;
        }
    }
}
