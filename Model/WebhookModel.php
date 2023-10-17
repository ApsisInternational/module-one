<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\WebhookResource;

/**
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method string getSubscriptionId()
 * @method $this setSubscriptionId(string $value)
 * @method int getType()
 * @method $this setType(int $value)
 * @method string getCallbackUrl()
 * @method $this setCallbackUrl(string $value)
 * @method string getFields()
 * @method $this setFields(string $value)
 * @method string getSecret()
 * @method $this setSecret(string $value)
 * @method string getBackoffConfig()
 * @method $this setBackoffConfig(string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 *
 */
class WebhookModel extends AbstractModel
{
    const RESOURCE_MODEL = WebhookResource::class;

    const TYPE_RECORD = 1;
    const TYPE_CONSENT = 2;
    const TYPE_TEXT_MAP = [
        self::TYPE_RECORD => 'Record',
        self::TYPE_CONSENT => 'Consent'
    ];
}
