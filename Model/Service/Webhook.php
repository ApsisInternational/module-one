<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\Webhook as WebhookModel;
use Apsis\One\Model\WebhookFactory;
use Apsis\One\Model\ResourceModel\Webhook as WebhookResource;
use Apsis\One\Model\ResourceModel\Webhook\CollectionFactory as WebhookCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Throwable;

class Webhook
{
    /**
     * @var WebhookFactory
     */
    private WebhookFactory $webhookFactory;

    /**
     * @var WebhookResource
     */
    private WebhookResource $webhookResource;

    /**
     * @var WebhookCollectionFactory
     */
    private WebhookCollectionFactory $webhookCollectionFactory;

    /**
     * @param WebhookFactory $webhookFactory
     * @param WebhookResource $webhookResource
     * @param WebhookCollectionFactory $webhookCollectionFactory
     */
    public function __construct(
        WebhookFactory $webhookFactory,
        WebhookResource $webhookResource,
        WebhookCollectionFactory $webhookCollectionFactory
    ) {
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->webhookFactory = $webhookFactory;
        $this->webhookResource = $webhookResource;
    }

    /**
     * @param string $subscriptionId
     * @param int $type
     * @param Core $coreHelper
     *
     * @return int|WebhookModel
     */
    private function getWebhookObject(string $subscriptionId, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $collection = $this->webhookCollectionFactory->create()
                ->addFieldToFilter('subscription_id', $subscriptionId)
                ->addFieldToFilter('type', $type)
                ->setPageSize(1);
            return $collection->getSize() ? $collection->getFirstItem() : 404;
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param array $config
     * @param int $storeId
     * @param int $type
     * @param Core $coreHelper
     *
     * @return string|int
     */
    public function createWebhook(array $config, int $storeId, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $webhook = $this->webhookFactory->create()
                ->setStoreId($storeId)
                ->setType($type)
                ->setCallbackUrl($config['callback_url'])
                ->setFields(implode(',', $config[$type === WebhookModel::TYPE_RECORD ? 'fields' : 'consent_base_ids']))
                ->setSecret($config['secret']);
            $this->webhookResource->save($webhook);
            return $webhook->getSubscriptionId();
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param array $config
     * @param string $subscriptionId
     * @param int $type
     * @param Core $coreHelper
     *
     * @return bool|int
     */
    public function updateWebhook(array $config, string $subscriptionId, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $webhook = $this->getWebhookObject($subscriptionId, $type, $coreHelper);
            if (is_int($webhook)) {
                return $webhook;
            }

            $webhook->setCallbackUrl($config['callback_url'])
                ->setFields(
                    implode(',', $config[$type === WebhookModel::TYPE_RECORD ? 'fields' : 'consent_base_ids'])
                )
                ->setSecret($config['secret']);
            $this->webhookResource->save($webhook);
            return true;
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param string $subscriptionId
     * @param int $type
     * @param Core $coreHelper
     *
     * @return bool|int
     */
    public function deleteWebhook(string $subscriptionId, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $webhook = $this->getWebhookObject($subscriptionId, $type, $coreHelper);
            if (is_int($webhook)) {
                return $webhook;
            }

            $this->webhookResource->delete($webhook);
            return true;
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param string $subscriptionId
     * @param int $type
     * @param Core $coreHelper
     *
     * @return array|int
     */
    public function getWebhook(string $subscriptionId, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $webhook = $this->getWebhookObject($subscriptionId, $type, $coreHelper);
            if (is_int($webhook)) {
                return $webhook;
            }

            return [
                'subscription_id' => $webhook->getSubscriptionId(),
                'callback_url' => $webhook->getCallbackUrl(),
                'entity' => 'profile',
                $type === WebhookModel::TYPE_RECORD ? 'fields' : 'consent_base_ids' =>
                    explode(',', $webhook->getFields())
            ];
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param string $callbackUrl
     * @param int $type
     * @param Core $coreHelper
     *
     * @return string|int|false
     */
    public function findWebhookSubscriptionId(string $callbackUrl, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $collection = $this->webhookCollectionFactory->create()
                ->addFieldToFilter('callback_url', $callbackUrl)
                ->addFieldToFilter('type', $type)
                ->setPageSize(1);
            return $collection->getSize() ? $collection->getFirstItem()->getSubscriptionId() : false;
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param int $storeId
     * @param int $type
     * @param Core $coreHelper
     *
     * @return array|int
     */
    public function getAllWebhooksForStoreByType(int $storeId, int $type, ApsisCoreHelper $coreHelper)
    {
        try {
            $collection = $this->webhookCollectionFactory->create()
                ->addFieldToFilter('type', $type)
                ->addFieldToFilter('store_id', $storeId);
            $webhooks = [];
            /** @var WebhookModel $item */
            foreach ($collection as $item) {
                $webhooks[] = [
                    'subscription_id' => $item->getSubscriptionId(),
                    'callback_url' => $item->getCallbackUrl(),
                    'entity' => 'profile',
                    $type === WebhookModel::TYPE_RECORD ? 'fields' : 'consent_base_ids' =>
                        explode(',', $item->getFields()),
                ];
            }
            return $webhooks;
        } catch (Throwable $e) {
            $coreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }
}
