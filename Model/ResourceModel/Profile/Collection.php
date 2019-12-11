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
     * @param string $email
     * @param int $storeId
     *
     * @return bool|DataObject
     */
    public function loadSubscriberByEmailAndStoreId(string $email, int $storeId)
    {
        $collection = $this->addFieldToFilter('email', $email)
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('is_subscriber', 1)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param int $customerId
     *
     * @return bool|DataObject
     */
    public function loadCustomerById(int $customerId)
    {
        $collection = $this->addFieldToFilter('customer_id', $customerId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param string $email
     * @param int $storeId
     *
     * @return bool|DataObject
     */
    public function loadByEmailAndStoreId(string $email, int $storeId)
    {
        $collection = $this->addFieldToFilter('email', $email)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param int $storeId
     * @param int $syncLimit
     *
     * @return Collection
     */
    public function getSubscribersToBatchByStore(int $storeId, int $syncLimit)
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('subscriber_id', ['notnull' => true])
            ->addFieldToFilter('subscriber_sync_status', Profile::SYNC_STATUS_PENDING)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize($syncLimit);
    }

    /**
     * @param int $storeId
     * @param int $syncLimit
     *
     * @return Collection
     */
    public function getCustomerToBatchByStore(int $storeId, int $syncLimit)
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
    public function getCollectionFromIds(array $ids)
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter('id', ['in' => $ids]);
    }
}
