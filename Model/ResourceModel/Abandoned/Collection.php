<?php

namespace Apsis\One\Model\ResourceModel\Abandoned;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\Abandoned;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected $_idFieldName = 'id';

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(Abandoned::class, AbandonedResource::class);
    }

    /**
     * @param string $token
     *
     * @return bool|DataObject
     */
    public function loadByToken(string $token)
    {
        $collection = $this->addFieldToFilter('token', $token)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @param int $profileId
     * @param int $storeId
     *
     * @return bool|DataObject
     */
    public function loadByProfileIdAndStoreId(int $profileId, int $storeId)
    {
        $collection = $this->addFieldToFilter('profile_id', $profileId)
            ->addFieldToFilter('store_id', $storeId)
            ->setOrder($this->_idFieldName)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }
}
