<?php

namespace Apsis\One\Controller\Api\Consents;

use Apsis\One\Controller\Api\AbstractProfile;
use Magento\Framework\App\ResponseInterface;
use Throwable;

class Index extends AbstractProfile
{
    const CONSENT_BASE_ID = '1';
    const CONSENT_BASES = [
        ['id' => self::CONSENT_BASE_ID, 'name' => 'Newsletter Subscriber']
    ];

    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = [
        'ProfileConsentBases' => ['GET', 'HEAD'],
        'ProfileConsents' => ['GET', 'HEAD', 'PATCH'],
        'ProfileConsentsCount' => ['GET', 'HEAD']
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getProfileConsentBases' => ['query' => ['page_size' => 'int', 'page' => 'int']],
        'getProfileConsents' => ['query' => ['page_size' => 'int', 'page' => 'int']],
        'patchProfileConsents' => [
            'query' => [],
            'post' => ['consent_base_id' => 'string', 'record_id' => 'string', 'has_consented' => 'bool']
        ],
        'getProfileConsentsCount' => ['query' => []]
    ];

    /**
     * @return ResponseInterface
     */
    protected function getProfileConsentBases(): ResponseInterface
    {
        try {
            return $this->sendResponse(200, null, json_encode(self::CONSENT_BASES));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileConsents(): ResponseInterface
    {
        try {
            $consents = $this->getConsents();
            if (is_int($consents)) {
                return $this->sendErrorInResponse($consents);
            }
            return $this->sendResponse(200, null, json_encode($consents));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileConsentsCount(): ResponseInterface
    {
        try {
            $count = $this->getConsentsCount();
            if (is_int($count)) {
                return $this->sendErrorInResponse($count);
            }
            return $this->sendResponse(200, null, json_encode($count));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function patchProfileConsents(): ResponseInterface
    {
        try {
            $status = $this->updateConsent();
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }
            return $this->sendResponse(200);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
