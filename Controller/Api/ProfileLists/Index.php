<?php

namespace Apsis\One\Controller\Api\ProfileLists;

use Apsis\One\Controller\Api\AbstractProfile;
use Magento\Framework\App\ResponseInterface;
use Throwable;

class Index extends AbstractProfile
{
    /**
     * @inheirtDoc
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @inheirtDoc
     */
    protected array $allowedHttpMethods = ['ProfileLists' => ['GET', 'HEAD']];

    /**
     * @inheirtDoc
     */
    protected array $requiredParams = ['getProfileLists' => ['query' => ['page_size' => 'int', 'page' => 'int']]];

    /**
     * @return ResponseInterface
     */
    protected function getProfileLists(): ResponseInterface
    {
        try {
            $collection = $this->groupCollectionFactory->create()->setRealGroupsFilter();
            $collection = $this->setPaginationOnCollection($collection, 'customer_group_id');
            if (is_int($collection)) {
                return $this->sendErrorInResponse(500);
            }

            $groups = [];
            foreach ($collection as $item) {
                $groups[] = [
                    'list_name' => $item->getCustomerGroupCode(),
                    'list_id' => $item->getCustomerGroupId(),
                    'list_entity' => 'profile',
                    'is_dynamic' => false
                ];
            }
            return $this->sendResponse(200, null, json_encode($groups));
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }
}
