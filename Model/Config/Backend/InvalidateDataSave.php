<?php

namespace Apsis\One\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * Prevents from saving data to database
 *
 * Class InvalidateDataSave
 */
class InvalidateDataSave extends Value
{
    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        $this->_dataSaveAllowed = false;
        return $this;
    }
}
