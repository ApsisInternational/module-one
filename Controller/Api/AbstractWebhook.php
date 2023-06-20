<?php

namespace Apsis\One\Controller\Api;

use Magento\Customer\Model\CustomerFactory;
use Apsis\One\Service\WebhookService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Throwable;

abstract class AbstractWebhook extends AbstractApi
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param WebhookService $service
     * @param CustomerFactory $customerFactory
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        WebhookService $service,
        CustomerFactory $customerFactory,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($request, $response, $service, $customerFactory, $encryptor);
    }

    /**
     * @param int $type
     *
     * @return ResponseInterface
     */
    protected function deleteWebhookByType(int $type): ResponseInterface
    {
        try {
            $status = $this->service->deleteWebhook($this->taskId, $type);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }
            return $this->sendResponse(204);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
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
            $status = $this->service->updateWebhook($this->requestBody, $this->taskId, $type);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }
            return $this->sendResponse(204);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
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
            $subscriptionId = $this->service
                ->getWebhookCollection()
                ->findWebhookByCallbackUrlForType($this->requestBody['callback_url'], $type, $this->service);
            if (is_int($subscriptionId)) {
                return $this->sendErrorInResponse($subscriptionId);
            }
            if (strlen((string) $subscriptionId)) {
                $data = ['subscription_id' => $subscriptionId];
                return $this->sendResponse(409, null, json_encode($data));
            }

            $subscriptionId = $this->service->createWebhook($this->requestBody, $this->store->getId(), $type);
            if (is_int($subscriptionId)) {
                return $this->sendErrorInResponse($subscriptionId);
            }
            $data = ['subscription_id' => $subscriptionId];
            return $this->sendResponse(201, null, json_encode($data));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
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
            $record = $this->service->getWebhook($this->taskId, $type);
            if (is_int($record)) {
                return $this->sendErrorInResponse($record);
            }
            return $this->sendResponse(200, null, json_encode($record));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
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
            $records = $this->service->getWebhookForStoreByType($this->store->getId(), $type);
            if (is_int($records)) {
                return $this->sendErrorInResponse($records);
            }
            return $this->sendResponse(200, null, json_encode($records));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
