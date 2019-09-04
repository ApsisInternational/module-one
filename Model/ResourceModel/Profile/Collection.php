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
     * @return bool|DataObject
     */
    public function loadSubscriberByEmailAndStoreId($email, $storeId)
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
     * @return bool|DataObject
     */
    public function loadCustomerById($customerId)
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
     * @return bool|DataObject
     */
    public function loadByEmailAndStoreId($email, $storeId)
    {
        $collection = $this->addFieldToFilter('email', $email)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }
}
