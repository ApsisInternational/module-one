<?php

namespace Apsis\One\Model\ResourceModel\Config;

use Apsis\One\Model\ConfigModel;
use Apsis\One\Model\ResourceModel\AbstractCollection;
use Apsis\One\Model\ResourceModel\ConfigResource;
use Apsis\One\Service\BaseService;

class ConfigCollection extends AbstractCollection
{
    const MODEL = ConfigModel::class;
    const RESOURCE_MODEL = ConfigResource::class;

    /**
     * @param string $sectionDiscriminator
     * @param int $storeId
     * @param BaseService $service
     * @param bool $fetchObject
     *
     * @return ConfigModel|ConfigCollection|int
     */
    public function findConfigBySectionForStore(
        string $sectionDiscriminator,
        int $storeId,
        BaseService $service,
        bool $fetchObject = true
    ): ConfigModel|ConfigCollection|int {
        try {
            $filters = ['store_id' => $storeId, 'section_discriminator' => explode(',', $sectionDiscriminator)];
            $collection = $this->getCollection($filters, null, $fetchObject ? 1 : 0);
            if ($collection->getSize()) {
                /** @var ConfigModel|ConfigCollection $item */
                $item = $fetchObject ? $collection->getFirstItem()->afterLoad() : $collection;
            } else {
                $item = 404;
            }
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 500;
        }
        return $item;
    }

    /**
     * @param int $storeId
     * @param BaseService $service
     *
     * @return ConfigModel|int
     */
    public function findAConfigForStore(int $storeId, BaseService $service): ConfigModel|int
    {
        try {
            $collection = $this->getCollection('store_id', $storeId, 1);
            if ($collection->getSize()) {
                /** @var ConfigModel $item */
                $item = $collection->getFirstItem()->afterLoad();
            } else {
                $item = 404;
            }
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 500;
        }
        return $item;
    }

    /**
     * @param int $storeId
     *
     * @return ConfigCollection
     */
    public function getActiveConfigForStore(int $storeId): ConfigCollection
    {
        return $this->getCollection(['storeId' => $storeId, 'is_active' => 1], null, 1);
    }
}
