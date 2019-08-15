<?php

namespace Apsis\One\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory class for creating DateInterval object
 */
class DateIntervalFactory
{
    /**
     * Object Manager instance
     *
     * @var ObjectManagerInterface
     */
    private $_objectManager = null;

    /**
     * @var null|string
     */
    private $_instanceName  = null;

    /**
     * Factory constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(ObjectManagerInterface $objectManager, $instanceName = '\\DateInterval')
    {
        $this->_instanceName = $instanceName;
        $this->_objectManager = $objectManager;
    }

    /**
     * Create DateInterval object with specified parameters
     *
     * @param array $data
     * @return \DateInterval
     */
    public function create(array $data = [])
    {
        return $this->_objectManager->create('\\DateInterval', $data);
    }
}
