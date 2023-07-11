<?php

namespace Apsis\One\Service;

use Apsis\One\Logger\Logger;
use Apsis\One\Model\ResourceModel\Webhook\WebhookCollection;
use Apsis\One\Model\WebhookModel;
use Apsis\One\Model\WebhookModelFactory;
use Apsis\One\Model\ResourceModel\WebhookResource;
use Apsis\One\Model\ResourceModel\Webhook\WebhookCollectionFactory;
use Apsis\One\Service\Sub\SubWebhookService;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class WebhookService extends BaseService
{
    /**
     * @var WebhookModelFactory
     */
    private WebhookModelFactory $webhookModelFactory;

    /**
     * @var WebhookResource
     */
    private WebhookResource $webhookResource;

    /**
     * @var WebhookCollectionFactory
     */
    private WebhookCollectionFactory $webhookCollectionFactory;

    /**
     * @var SubWebhookService
     */
    private SubWebhookService $subWebhookService;

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param ModuleListInterface $moduleList
     * @param SubWebhookService $subWebhookService
     * @param WebhookModelFactory $webhookModelFactory
     * @param WebhookResource $webhookResource
     * @param WebhookCollectionFactory $webhookCollectionFactory
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        ModuleListInterface $moduleList,
        SubWebhookService $subWebhookService,
        WebhookModelFactory $webhookModelFactory,
        WebhookResource $webhookResource,
        WebhookCollectionFactory $webhookCollectionFactory
    ) {
        parent::__construct($logger, $storeManager, $writer, $moduleList);
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->webhookModelFactory = $webhookModelFactory;
        $this->webhookResource = $webhookResource;
        $this->subWebhookService = $subWebhookService;
    }

    /**
     * @return WebhookCollection
     */
    public function getWebhookCollection(): WebhookCollection
    {
        return $this->webhookCollectionFactory->create();
    }

    /**
     * @return WebhookModel
     */
    private function getWebhookModel(): WebhookModel
    {
        return $this->webhookModelFactory->create();
    }

    /**
     * @param array $config
     * @param int $storeId
     * @param int $type
     *
     * @return string|int
     */
    public function createWebhook(array $config, int $storeId, int $type): int|string
    {
        try {
            $webhook = $this->getWebhookCollection()
                ->getWebhookObject('', $type, $this, $storeId);
            if ($webhook === 500) {
                return $webhook;
            } elseif ($webhook === 404) {
                $webhook = $this->getWebhookModel();
            }

            $webhook->setStoreId($storeId)
                ->setType($type)
                ->setCallbackUrl($config['callback_url'])
                ->setFields(implode(',', $config[$type === WebhookModel::TYPE_RECORD ? 'fields' : 'consent_base_ids']))
                ->setSecret($config['secret']);
            $this->webhookResource->save($webhook);
            return (string) $webhook->getSubscriptionId();
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param array $config
     * @param string $subscriptionId
     * @param int $type
     *
     * @return bool|int
     */
    public function updateWebhook(array $config, string $subscriptionId, int $type): bool|int
    {
        try {
            $webhook = $this->getWebhookCollection()
                ->getWebhookObject($subscriptionId, $type, $this);
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
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param string $subscriptionId
     * @param int $type
     *
     * @return bool|int
     */
    public function deleteWebhook(string $subscriptionId, int $type): bool|int
    {
        try {
            $webhook = $this->getWebhookCollection()
                ->getWebhookObject($subscriptionId, $type, $this);
            if (is_int($webhook)) {
                return $webhook;
            }

            $this->webhookResource->delete($webhook);
            return true;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param string $subscriptionId
     * @param int $type
     *
     * @return array|int
     */
    public function getWebhook(string $subscriptionId, int $type): int|array
    {
        try {
            $webhook = $this->getWebhookCollection()
                ->getWebhookObject($subscriptionId, $type, $this);
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
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param int $storeId
     * @param int $type
     * @param bool $object
     *
     * @return array|int
     */
    public function getWebhookForStoreByType(int $storeId, int $type, bool $object = false): int|array
    {
        try {
            return $this->subWebhookService
                ->getWebhookForStoreByType($storeId, $type, $this, $this->getWebhookCollection(), $object);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }
}
