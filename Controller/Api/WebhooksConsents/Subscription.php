<?php

namespace Apsis\One\Controller\Api\WebhooksConsents;

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
    protected array $allowedHttpMethods = ['ProfileConsentsWebhook' => ['GET', 'HEAD', 'PATCH', 'DELETE']];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getProfileConsentsWebhook' => ['query' => []],
        'patchProfileConsentsWebhook' => [
            'post' => ['callback_url' => 'string', 'consent_base_ids' => 'array', 'secret' => 'string']
        ],
        'deleteProfileConsentsWebhook' => ['query' => []]
    ];

    /**
     * @return ResponseInterface
     */
    protected function getProfileConsentsWebhook(): ResponseInterface
    {
        return $this->getWebhookByType(Webhook::TYPE_CONSENT);
    }

    /**
     * @return ResponseInterface
     */
    protected function patchProfileConsentsWebhook(): ResponseInterface
    {
        return $this->patchWebhookByType(Webhook::TYPE_CONSENT);
    }

    /**
     * @return ResponseInterface
     */
    protected function deleteProfileConsentsWebhook(): ResponseInterface
    {
        return $this->deleteWebhookByType(Webhook::TYPE_CONSENT);
    }
}