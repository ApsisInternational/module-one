<?php

namespace Apsis\One\Controller\Api\Consents;

use Apsis\One\Controller\Api\AbstractProfile;
use Magento\Framework\App\ResponseInterface;

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
            'post' => ['consent_base_id' => 'string', 'record_id' => 'string', 'has_consented' => 'bool']
        ],
        'getProfileConsentsCount' => ['query' => []]
    ];

    /**
     * @return ResponseInterface
     */
    protected function getProfileConsentBases(): ResponseInterface
    {
        return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize(self::CONSENT_BASES));
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileConsents(): ResponseInterface
    {
        $consents = $this->getConsents();
        if (is_int($consents)) {
            return $this->sendErrorInResponse($consents);
        }
        return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize($consents));
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileConsentsCount(): ResponseInterface
    {
        $count = $this->getConsentsCount();
        if (is_int($count)) {
            return $this->sendErrorInResponse($count);
        }
        return $this->sendResponse(200, null, $this->apsisCoreHelper->serialize($count));
    }

    /**
     * @return ResponseInterface
     */
    protected function patchProfileConsents(): ResponseInterface
    {
        $status = $this->updateConsent();
        if (is_int($status)) {
            return $this->sendErrorInResponse($status);
        }
        return $this->sendResponse(200);
    }
}
