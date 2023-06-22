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
            return (string) $this->encryptor
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
                    return $this->saveTokenConfigAndReturn($store, $response, $apiService);
                }

                //Error in generating token, remove API configs
                if ($response && isset($response->status) &&
                    in_array($response->status, AbstractRestApi::HTTP_CODES_DISABLE_MODULE)
                ) {
                    $apiService->debug(__METHOD__, (array) $response);
                    $configs = [
                        BaseService::PATH_APSIS_CLIENT_ID => '',
                        BaseService::PATH_APSIS_CLIENT_SECRET => '',
                        BaseService::PATH_APSIS_API_TOKEN_EXPIRY => '',
                        BaseService::PATH_APSIS_API_TOKEN => '',
                    ];
                    $check = $apiService->saveStoreConfig($store, $configs);
                    $apiService->log(
                        $check === true ? 'All API configs removed' : 'Unable to remove api configs',
                        ['Store Id' => $store->getId()]
                    );
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
     * @param stdClass $response
     * @param ApiService $apiService
     *
     * @return string
     */
    public function saveTokenConfigAndReturn(StoreInterface $store, stdClass $response, ApiService $apiService): string
    {
        try {
            $encryptedToken = $this->encryptor->encrypt($response->access_token);
            if (empty($encryptedToken)) {
                return '';
            }

            $check = $apiService->saveStoreConfig($store, [BaseService::PATH_APSIS_API_TOKEN => $encryptedToken]);
            if ($check !== true) {
                return '';
            }

            $time = $apiService->getDateTimeFromTimeAndTimeZone()
                ->add($apiService->getDateIntervalFromIntervalSpec(sprintf('PT%sS', $response->expires_in)))
                ->sub($apiService->getDateIntervalFromIntervalSpec('PT60M'))
                ->format('Y-m-d H:i:s');

            $check = $apiService->saveStoreConfig($store, [BaseService::PATH_APSIS_API_TOKEN_EXPIRY => $time]);
            if ($check !== true) {
                return '';
            }

            return (string) $response->access_token;
        } catch (Throwable $e) {
            $apiService->logError(__METHOD__, $e);
            return '';
        }
    }
}
