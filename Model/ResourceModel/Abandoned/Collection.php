<?php

namespace Apsis\One\Model\ResourceModel\Abandoned;

use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\Abandoned;

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
        $this->_init(Abandoned::class, AbandonedResource::class);
    }

    /**
     * @param int $quoteId
     *
     * @return bool|DataObject
     */
    public function loadByQuoteId(int $quoteId)
    {
        $collection = $this->addFieldToFilter('quote_id', $quoteId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }
}
