<?php

namespace Apsis\One\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;

class Event extends AbstractModel
{
    /**
     * Event types / names
     */
    const EVENT_TYPE_CUSTOMER_ABANDONED_CART = 1;
    const EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER = 2;
    const EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER = 3;
    const EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE = 4;
    const EVENT_TYPE_CUSTOMER_LOGIN = 5;
    const EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER = 6;
    const EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW = 7;
    const EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST = 8;
    const EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART = 9;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * Event constructor.
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
     */
    public function _construct()
    {
        $this->_init(EventResource::class);
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
