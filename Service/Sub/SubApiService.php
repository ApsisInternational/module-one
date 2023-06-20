<?php

namespace Apsis\One\Service\Sub;

use Apsis\One\Service\Api\ClientApi;
use Apsis\One\Service\ApiService;
use Apsis\One\Service\BaseService;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Service\Api\AbstractRestApi;
use stdClass;
use Throwable;

class SubApiService
{
    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param EncryptorInterface $encryptor
     */
    public function __construct(EncryptorInterface $encryptor)
    {
        $this->encryptor = $encryptor;
    }

    /**
     * @param StoreInterface $store
     * @param ApiService $apiService
     *
     * @return string|null
     */
    public function getClientId(StoreInterface $store, ApiService $apiService): ?string
    {
        try {
            return $apiService->getStoreConfig($store, BaseService::PATH_APSIS_CLIENT_ID);
        } catch (Throwable $e) {
            $apiService->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param StoreInterface $store
     * @param ApiService $apiService
     *
     * @return string|null
     */
    public function getClientSecret(StoreInterface $store, ApiService $apiService): ?string
    {
        try {
            return $this->encryptor
                ->decrypt($apiService->getStoreConfig($store, BaseService::PATH_APSIS_CLIENT_SECRET));
        } catch (Throwable $e) {
            $apiService->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param StoreInterface $store
     * @param ApiService $apiService
     *
     * @return string|null
     */
    public function getApiUrl(StoreInterface $store, ApiService $apiService): ?string
    {
        try {
            return $apiService->getStoreConfig($store, BaseService::PATH_APSIS_API_URL);
        } catch (Throwable $e) {
            $apiService->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param ClientApi $apiClient
     * @param StoreInterface $store
     * @param ApiService $apiService
     *
     * @return string
     */
    public function getToken(ClientApi $apiClient, StoreInterface $store, ApiService $apiService): string
    {
        $token = '';
        try {
            // First fetch from DB
            $token = $this->encryptor->decrypt($apiService->getStoreConfig($store, BaseService::PATH_APSIS_API_TOKEN));
            if (empty($token) || $this->isTokenExpired($store, $apiService)) {
                $response = $apiClient->getAccessToken();

                //Success in generating token
                if ($response && isset($response->access_token)) {
                    $apiService->debug('Token renewed', ['Store Id' => $store->getId()]);
                    $this->saveTokenAndExpiry($store, $response, $apiService);
                    return (string) $response->access_token;
                }

                //Error in generating token, remove API configs
                if ($response && isset($response->status) &&
                    in_array($response->status, AbstractRestApi::HTTP_CODES_DISABLE_MODULE)
                ) {
                    $apiService->debug(__METHOD__, (array) $response);
                    $apiService->saveStoreConfig($store, BaseService::PATH_APSIS_CLIENT_ID, '');
                    $apiService->saveStoreConfig($store, BaseService::PATH_APSIS_CLIENT_SECRET, '');
                    $apiService->saveStoreConfig($store, BaseService::PATH_APSIS_API_TOKEN_EXPIRY, '');
                    $apiService->saveStoreConfig($store, BaseService::PATH_APSIS_API_TOKEN, '');
                    $apiService->log('All API configs removed', ['Store Id' => $store->getId()]);
                }
            }
        } catch (Throwable $e) {
            $apiService->logError(__METHOD__, $e);
        }
        return $token;
    }

    /**
     * @param StoreInterface $store
     * @param ApiService $apiService
     *
     * @return bool
     */
    public function isTokenExpired(StoreInterface $store, ApiService $apiService): bool
    {
        try {
            $expiryTime = $apiService->getStoreConfig($store, BaseService::PATH_APSIS_API_TOKEN_EXPIRY);
            $nowTime = $apiService->getDateTimeFromTimeAndTimeZone()
                ->add($apiService->getDateIntervalFromIntervalSpec('PT15M'))
                ->format('Y-m-d H:i:s');

            $check = ($nowTime > $expiryTime);

            if ($check) {
                $info = [
                    'Store Id' => $store->getId(),
                    'Is Expired/Empty' => true,
                    'Last Expiry DateTime' => $expiryTime
                ];
                $apiService->debug(__METHOD__, $info);
            }

            return $check;
        } catch (Throwable $e) {
            $apiService->logError(__METHOD__, $e);
            return true;
        }
    }

    /**
     * @param StoreInterface $store
     * @param stdClass $request
     * @param ApiService $apiService
     *
     * @return void
     */
    public function saveTokenAndExpiry(StoreInterface $store, stdClass $request, ApiService $apiService): void
    {
        try {
            $apiService->saveStoreConfig(
                $store,
                BaseService::PATH_APSIS_API_TOKEN,
                $this->encryptor->encrypt($request->access_token)
            );

            $time = $apiService->getDateTimeFromTimeAndTimeZone()
                ->add($apiService->getDateIntervalFromIntervalSpec(sprintf('PT%sS', $request->expires_in)))
                ->sub($apiService->getDateIntervalFromIntervalSpec('PT60M'))
                ->format('Y-m-d H:i:s');

            $apiService->saveStoreConfig($store, BaseService::PATH_APSIS_API_TOKEN_EXPIRY, $time);
        } catch (Throwable $e) {
            $apiService->logError(__METHOD__, $e);
        }
    }
}
