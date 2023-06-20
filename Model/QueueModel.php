<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\QueueResource;

/**
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getProfileId()
 * @method $this setProfileId(int $value)
 * @method int getType()
 * @method $this setType(int $value)
 * @method int getSyncStatus()
 * @method $this setSyncStatus(int $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */
class QueueModel extends AbstractModel
{
    const RESOURCE_MODEL = QueueResource::class;

    const RECORD_CREATED = 1;
    const RECORD_UPDATED = 2;
    const RECORD_DELETED = 3;
    const CONSENT_OPT_IN = 4;
    const CONSENT_OPT_OUT = 5;

    const TYPE_TEXT_MAP = [
        self::RECORD_CREATED => 'RECORD: Created',
        self::RECORD_UPDATED => 'RECORD: Updated',
        self::RECORD_DELETED => 'RECORD: Deleted',
        self::CONSENT_OPT_IN => 'CONSENT: Opt-in',
        self::CONSENT_OPT_OUT => 'CONSENT: Opt-out'
    ];

    const STATUS_EXPIRED = 3;
    const STATUS_TEXT_MAP = [
        self::STATUS_EXPIRED => 'Expired'
    ];
}
