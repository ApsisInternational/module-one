<?php

namespace Apsis\One\Controller\Api\ProfileLists;

use Apsis\One\Controller\Api\AbstractProfile;
use Magento\Framework\App\ResponseInterface;
use Throwable;

class Records extends AbstractProfile
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = true;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = [
        'ProfileListsRecords' => ['GET', 'HEAD'],
        'ProfileListsRecordsCount' => ['GET', 'HEAD']
    ];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = [
        'getProfileListsRecords' => ['query' => ['page_size' => 'int', 'page' => 'int']],
        'getProfileListsRecordsCount' => ['query' => []]
    ];

    /**
     * @return ResponseInterface
     */
    protected function getProfileListsRecords(): ResponseInterface
    {
        try {
            $status = $this->doesGroupIdExist((int) $this->taskId);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }

            $records = $this->getGroupRecords();
            if (is_int($records)) {
                return $this->sendErrorInResponse($records);
            }
            return $this->sendResponse(200, null, json_encode($records));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return ResponseInterface
     */
    protected function getProfileListsRecordsCount(): ResponseInterface
    {
        try {
            $status = $this->doesGroupIdExist((int) $this->taskId);
            if (is_int($status)) {
                return $this->sendErrorInResponse($status);
            }

            $count = $this->getGroupRecordsCount();
            if (is_int($count)) {
                return $this->sendErrorInResponse($count);
            }
            return $this->sendResponse(200, null, json_encode($count));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
