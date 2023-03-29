<?php

namespace Apsis\One\Model\ResourceModel\Webhook;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apsis\One\Model\Webhook;
use Apsis\One\Model\ResourceModel\Webhook as WebhookResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_idFieldName = 'id';
        $this->_init(Webhook::class, WebhookResource::class);
    }
}
