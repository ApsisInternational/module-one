<?php

namespace Apsis\One\Controller\Api\WebhooksRecords;

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
        'ProfileRecordsWebhook' => ['POST'],
        'AllRecordsWebhook' => ['GET', 'HEAD']
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'postProfileRecordsWebhook' => [
            'query' => [],
            'post' => ['callback_url' => 'string', 'fields' => 'array', 'secret' => 'string']
        ],
        'getAllRecordsWebhook' => ['query' => ['entity' => 'string']]
    ];

    /**
     * @return ResponseInterface
     */
    protected function postProfileRecordsWebhook(): ResponseInterface
    {
        return $this->postWebhookByType(WebhookModel::TYPE_RECORD);
    }

    /**
     * @return ResponseInterface
     */
    protected function getAllRecordsWebhook(): ResponseInterface
    {
        return $this->getAllWebhooksByType(WebhookModel::TYPE_RECORD);
    }
}
