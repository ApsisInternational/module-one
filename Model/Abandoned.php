<?php

namespace Apsis\One\Model;

use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

class Abandoned extends AbstractModel
{
    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * Abandoned constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->dateTime = $dateTime;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Constructor.
     *
     * @return null
     */
    public function _construct()
    {
        $this->_init(AbandonedResource::class);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($this->dateTime->formatDate(true));
        }
        $this->setUpdatedAt($this->dateTime->formatDate(true));

        return $this;
    }
}