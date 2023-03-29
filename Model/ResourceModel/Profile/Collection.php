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
     * @return DataObject|Profile|bool
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
     * @param array $ids
     * @param string $idField
     *
     * @return Collection
     */
    public function getCollectionFromIds(array $ids, string $idField = 'id'): Collection
    {
        return $this->addFieldToSelect('*')
            ->addFieldToFilter($idField, ['in' => $ids]);
    }

    /**
     * @param int $storeId
     *
     * @return Collection
     */
    public function getProfileCollectionForStore(int $storeId): Collection
    {
        return $this->addFieldToFilter('store_id', ['eq' => $storeId])
            ->addFieldToFilter('email', ['notnull' => true]);
    }
}
