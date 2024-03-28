<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Service\BaseService;
use Magento\Framework\Model\AbstractModel;

interface EntityDataInterface
{
    /**
     * @param AbstractModel $model
     * @param BaseService $baseService
     *
     * @return array
     */
    public function getDataArr(AbstractModel $model, BaseService $baseService): array;
}
