<?php

namespace Apsis\One\Controller\Api\WebhooksRecords;

use Apsis\One\Controller\Api\AbstractWebhook;
use Apsis\One\Model\Webhook;
use Magento\Framework\App\ResponseInterface;

class Subscription extends AbstractWebhook
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = true;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = ['ProfileRecordsWebhook' => ['GET', 'HEAD', 'PATCH', 'DELETE']];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getProfileRecordsWebhook' => ['query' => []],
        'patchProfileRecordsWebhook' => [
            'post' => ['callback_url' => 'string', 'fields' => 'array', 'secret' => 'string']
        ],
        'deleteProfileRecordsWebhook' => ['query' => []]
    ];

    /**
     * @return ResponseInterface
     */
    protected function getProfileRecordsWebhook(): ResponseInterface
    {
        return $this->getWebhookByType(Webhook::TYPE_RECORD);
    }

    /**
     * @return ResponseInterface
     */
    protected function patchProfileRecordsWebhook(): ResponseInterface
    {
        return $this->patchWebhookByType(Webhook::TYPE_RECORD);
    }

    /**
     * @return ResponseInterface
     */
    protected function deleteProfileRecordsWebhook(): ResponseInterface
    {
        return $this->deleteWebhookByType(Webhook::TYPE_RECORD);
    }
}
