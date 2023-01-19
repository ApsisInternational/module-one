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
    public function _construct()
    {
        $this->_idFieldName = 'id';
        $this->_init(Abandoned::class, AbandonedResource::class);
    }

    /**
     * @param string $token
     *
     * @return DataObject|Abandoned|bool
     */
    public function loadByToken(string $token): DataObject|Abandoned|bool
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
     * @return DataObject|Abandoned|bool
     */
    public function loadByProfileIdAndStoreId(int $profileId, int $storeId): DataObject|Abandoned|bool
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
