<?php

namespace Apsis\One\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

class Attributes extends Value
{
    /**
     * @return $this|Attributes
     */
    public function beforeSave()
    {
        if ($this->_registry->registry(Section::REGISTRY_NAME)) {
            $this->_dataSaveAllowed = false;
            return $this;
        }
        return parent::beforeSave();
    }
}
