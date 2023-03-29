<?php

namespace Apsis\One\Controller\Api;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Action\Context;
use Apsis\One\Model\Service\Webhook;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Escaper;
use Throwable;

abstract class AbstractWebhook extends AbstractApi
{
    /**
     * @var Webhook
     */
    protected Webhook $webhookService;

    /**
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param CustomerFactory $customerFactory
     * @param Escaper $escaper
     * @param Webhook $webhookService
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        CustomerFactory $customerFactory,
        Escaper $escaper,
        Webhook $webhookService
    ) {
        $this->webhookService = $webhookService;
        parent::__construct($context, $apsisCoreHelper, $customerFactory, $escaper);
    }

    /**
     * @param int $type
     *
     * @return ResponseInterface
     */
    protected function deleteWebhookByType(int $type): ResponseInterface
    {
        try {
            $status = $this->webhookService->deleteWebhook($this->taskId, $type, $this->apsisCoreHelper);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }
            return $this->sendResponse(204);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @param int $type
     *
     * @return ResponseInterface
     */
    protected function patchWebhookByType(int $type): ResponseInterface
    {
        try {
            $status = $this->webhookService
                ->updateWebhook($this->requestBody, $this->taskId, $type, $this->apsisCoreHelper);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }
            return $this->sendResponse(204);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @param int $type
     *
     * @return ResponseInterface
     */
    protected function postWebhookByType(int $type): ResponseInterface
    {
        try {
            $subscriptionId = $this->webhookService
                ->findWebhookSubscriptionId($this->requestBody['callback_url'], $type, $this->apsisCoreHelper);
            if (is_int($subscriptionId)) {
                return $this->sendErrorInResponse($subscriptionId);
            }
            if (strlen((string) $subscriptionId)) {
                $data = ['subscription_id' => $subscriptionId];
                return $this->sendResponse(409, null, $this->apsisCoreHelper->serialize($data));
            }

            $subscriptionId = $this->webhookService
                ->createWebhook($this->requestBody, $this->store->getId(), $type, $this->apsisCoreHelper);
            if (is_int($subscriptionId)) {
                return $this->sendErrorInResponse($subscriptionId);
            }
            $data = ['subscription_id' => $subscriptionId];
            return $this->sendResponse(201, null, $this->apsisCoreHelper->serialize($data));
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @param int $type
     *
     * @return ResponseInterface
     */
    protected function getWebhookByType(int $type): ResponseInterface
    {
        try {
            $record = $this->webhookService
                ->getWebhook($this->taskId, $type, $this->apsisCoreHelper);
            if (is_int($record)) {
                return $this->sendErrorInResponse($record);
            }
            return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize($record));
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @param int $type
     *
     * @return ResponseInterface
     */
    protected function getAllWebhooksByType(int $type): ResponseInterface
    {
        try {
            $records = $this->webhookService
                ->getAllWebhooksForStoreByType($this->store->getId(), $type, $this->apsisCoreHelper);
            if (is_int($records)) {
                return $this->sendErrorInResponse($records);
            }
            return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize($records));
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
