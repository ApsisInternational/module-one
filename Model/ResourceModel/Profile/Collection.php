<?php

namespace Apsis\One\Model\ResourceModel\Profile;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Profile;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Initialize resource collection.
     */
    public function _construct()
    {
        $this->_init(Profile::class, ProfileResource::class);
    }

    /**
     * @param int $customerId
     *
     * @return bool|DataObject|Profile
     */
    public function loadByCustomerId(int $customerId)
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
     * @return bool|DataObject
     */
    public function loadBySubscriberId(int $subscriberId)
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
     * @return bool|DataObject
     */
    public function loadByIntegrationId(string $integrationId)
    {
        $collection = $this->addFieldToFilter('integration_uid', $integrationId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param string $email
     * @param array $storeIds
     *
     * @return bool|DataObject|Profile
     */
    public function loadByEmailAndStoreId(string $email, array $storeIds)
    {
        $collection = $this->addFieldToFilter('email', $email)
            ->addFieldToFilter('store_id', ['in' => $storeIds])
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param array $storeIds
     * @param int $syncLimit
     * @param int $subscriberStatus
     *
     * @return Collection
     */
    public function getSubscribersToBatchByStore(array $storeIds, int $syncLimit, int $subscriberStatus)
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('subscriber_id', ['notnull' => true])
            ->addFieldToFilter('subscriber_status', $subscriberStatus)
            ->addFieldToFilter('subscriber_sync_status', Profile::SYNC_STATUS_PENDING)
            ->addFieldToFilter('store_id', ['in' => $storeIds])
            ->setPageSize($syncLimit);
    }

    /**
     * @param array $storeIds
     * @param int $syncLimit
     *
     * @return Collection
     */
    public function getCustomerToBatchByStore(array $storeIds, int $syncLimit)
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', ['notnull' => true])
            ->addFieldToFilter('customer_sync_status', Profile::SYNC_STATUS_PENDING)
            ->addFieldToFilter('store_id', ['in' => $storeIds])
            ->setPageSize($syncLimit);
    }

    /**
     * @param array $ids
     *
     * @return Collection
     */
    public function getCollectionFromIds(array $ids)
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('id', ['in' => $ids]);
    }

    /**
     * @param array $storeIds
     *
     * @return Collection
     */
    public function getProfileCollectionForStore(array $storeIds)
    {
        return $this->addFieldToFilter('store_id', ['in' => $storeIds])
            ->addFieldToFilter('email', ['notnull' => true]);
    }
}
