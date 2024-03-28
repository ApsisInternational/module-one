<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\EventResource;

/**
 * @method int getType()
 * @method $this setType(int $value)
 * @method string getEventData()
 * @method $this setEventData(string $value)
 * @method string getSubEventData()
 * @method $this setSubEventData(string $value)
 * @method int getProfileId()
 * @method $this setProfileId(int $value)
 * @method int getSubscriberId()
 * @method $this setSubscriberId(int $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getEmail()
 * @method $this setEmail(string $value)
 * @method int getSyncStatus()
 * @method $this setSyncStatus(int $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */
class EventModel extends AbstractModel
{
    const RESOURCE_MODEL = EventResource::class;

    const REVIEW = 1;
    const WISHED = 2;
    const CARTED = 3;
    const EVENT_CART_ABANDONED = 4;
    const ORDER = 5;
    const EVENT_SUBSCRIBER_UNSUBSCRIBED = 6;
    const EVENT_LOGGED_IN = 7;
    const EVENT_SUBSCRIBER_BECOMES_CUSTOMER = 8;
    const EVENT_CUSTOMER_BECOMES_SUBSCRIBER = 9;
    const TYPE_TEXT_MAP = [
        self::REVIEW => 'Product Reviewed',
        self::WISHED => 'Product Wished',
        self::CARTED => 'Product Carted',
        self::EVENT_CART_ABANDONED => 'Cart Abandoned',
        self::ORDER => 'Order Placed',
        self::EVENT_SUBSCRIBER_UNSUBSCRIBED => 'Subscriber Unsubscribed',
        self::EVENT_LOGGED_IN => 'Logged In',
        self::EVENT_SUBSCRIBER_BECOMES_CUSTOMER => 'Subscriber Becomes Customer',
        self::EVENT_CUSTOMER_BECOMES_SUBSCRIBER => 'Customer Becomes Subscriber'
    ];

    const STATUS_PENDING = 0;
    const STATUS_SYNCED = 1;
    const STATUS_FAILED = 2;
    const STATUS_HISTORICAL = 3;
    const STATUS_TEXT_MAP = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_SYNCED => 'Synced',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_HISTORICAL => 'Pending Historical'
    ];
}
