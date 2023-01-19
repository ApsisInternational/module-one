<?php

namespace Apsis\One\Model\ResourceModel\Profile;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Profile;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_idFieldName = 'id';
        $this->_init(Profile::class, ProfileResource::class);
    }

    /**
     * @param int $customerId
     *
     * @return DataObject|Profile|bool
     */
    public function loadByCustomerId(int $customerId): DataObject|Profile|bool
    {
        $collection = $this->addFieldToFilter('customer_id', $customerId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param int $subscriberId
     *
     * @return DataObject|Profile|bool
     */
    public function loadBySubscriberId(int $subscriberId): DataObject|Profile|bool
    {
        $collection = $this->addFieldToFilter('subscriber_id', $subscriberId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param string $integrationId
     *
     * @return DataObject|Profile|bool
     */
    public function loadByIntegrationId(string $integrationId): DataObject|Profile|bool
    {
        $collection = $this->addFieldToFilter('integration_uid', $integrationId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param int $storeId
     * @param int $syncLimit
     * @param int $subscriberStatus
     * @param array $syncStatus
     *
     * @return Collection
     */
    public function getSubscribersToBatchByStore(
        int $storeId,
        int $syncLimit,
        int $subscriberStatus,
        array $syncStatus
    ): Collection {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('subscriber_id', ['notnull' => true])
            ->addFieldToFilter('subscriber_status', $subscriberStatus)
            ->addFieldToFilter('subscriber_sync_status', ['in' => $syncStatus])
            ->addFieldToFilter('subscriber_store_id', $storeId)
            ->setPageSize($syncLimit);
    }

    /**
     * @param int $storeId
     * @param int $syncLimit
     *
     * @return Collection
     */
    public function getCustomerToBatchByStore(int $storeId, int $syncLimit): Collection
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', ['notnull' => true])
            ->addFieldToFilter('customer_sync_status', Profile::SYNC_STATUS_PENDING)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize($syncLimit);
    }

    /**
     * @param array $ids
     *
     * @return Collection
     */
    public function getCollectionFromIds(array $ids): Collection
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('id', ['in' => $ids]);
    }

    /**
     * @param int $storeId
     *
     * @return Collection
     */
    public function getProfileCollectionForStore(int $storeId): Collection
    {
        return $this->addFieldToFilter(
            ['store_id', 'subscriber_store_id'],
            [
                ['eq' => $storeId],
                ['eq' => $storeId]
            ]
        )->addFieldToFilter('email', ['notnull' => true]);
    }
}
