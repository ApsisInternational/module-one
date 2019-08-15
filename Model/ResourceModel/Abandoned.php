<?php

namespace Apsis\One\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Apsis\One\Helper\Core as Helper;

class Abandoned extends AbstractDb
{
    /**
     * Initialize resource.
     */
    public function _construct()
    {
        $this->_init(Helper::APSIS_ABANDONED_TABLE, 'id');
    }

    /**
     * @param array $carts
     *
     * @throws LocalizedException
     */
    public function insertAbandonedCarts(array $carts)
    {
        $write = $this->getConnection();
        if (! empty($carts)) {
            $write->insertMultiple($this->getMainTable(), $carts);
        }
    }
}
