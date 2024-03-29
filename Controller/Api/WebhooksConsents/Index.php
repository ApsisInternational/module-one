<?php

namespace Apsis\One\Controller\Api\WebhooksConsents;

use Apsis\One\Controller\Api\AbstractWebhook;
use Apsis\One\Model\WebhookModel;
use Magento\Framework\App\ResponseInterface;

class Index extends AbstractWebhook
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = [
        'ProfileConsentsWebhook' => ['POST'],
        'AllConsentsWebhook' => ['GET', 'HEAD']
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'postProfileConsentsWebhook' => [
            'query' => [],
            'post' => ['callback_url' => 'string', 'consent_base_ids' => 'array', 'secret' => 'string']
        ],
        'getAllConsentsWebhook' => ['query' => ['entity' => 'string']]
    ];

    /**
     * @return ResponseInterface
     */
    protected function postProfileConsentsWebhook(): ResponseInterface
    {
        return $this->postWebhookByType(WebhookModel::TYPE_CONSENT);
    }

    /**
     * @return ResponseInterface
     */
    protected function getAllConsentsWebhook(): ResponseInterface
    {
        return $this->getAllWebhooksByType(WebhookModel::TYPE_CONSENT);
    }
}
