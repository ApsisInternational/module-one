<?php

namespace Apsis\One\Model\Adminhtml\Config\Backend;

use Magento\Framework\App\Config\Value;

class InvalidateDataSaveBackendModel extends Value
{
    /**
     * @inheirtDoc
     */
    public function beforeSave(): static
    {
        $this->_dataSaveAllowed = false;
        return $this;
    }
}
