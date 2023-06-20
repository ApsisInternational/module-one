<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\AbandonedResource;

/**
 * @method int getQuoteId()
 * @method $this setQuoteId(int $value)
 * @method string getCartData()
 * @method $this setCartData(string $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method int getProfileId()
 * @method $this setProfileId(int $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method int getSubscriberId()
 * @method $this setSubscriberId(int $value)
 * @method string getEmail()
 * @method $this setEmail(string $value)
 * @method string getToken()
 * @method $this setToken(string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 */
class AbandonedModel extends AbstractModel
{
    const RESOURCE_MODEL = AbandonedResource::class;

    /**
     * @inheritDoc
     */
    public function beforeSave(): static
    {
        $this->setToken($this->getExpressionModel('(SELECT UUID())'))
            ->setCartData(json_encode($this->getCartData()));
        return parent::beforeSave();
    }
}
