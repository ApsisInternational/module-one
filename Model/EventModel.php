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

    const EVENT_TYPE_CUSTOMER_ABANDONED_CART = 1;
    const EVENT_TYPE_SUBSCRIBER_BECOMES_CUSTOMER = 2;
    const EVENT_TYPE_CUSTOMER_BECOMES_SUBSCRIBER = 3;
    const EVENT_TYPE_SUBSCRIBER_UNSUBSCRIBE = 4;
    const EVENT_TYPE_CUSTOMER_LOGIN = 5;
    const EVENT_TYPE_CUSTOMER_SUBSCRIBER_PLACED_ORDER = 6;
    const EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW = 7;
    const EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_WISHLIST = 8;
    const EVENT_TYPE_CUSTOMER_ADDED_PRODUCT_TO_CART = 9;

    const STATUS_PENDING = 0;
    const STATUS_SYNCED = 1;
    const STATUS_FAILED = 2;

    const STATUS_TEXT_MAP = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_SYNCED => 'Synced',
        self::STATUS_FAILED => 'Failed'
    ];
}
