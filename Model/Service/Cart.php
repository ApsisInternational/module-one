<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\Abandoned;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;
use Magento\Framework\DataObject;

class Cart
{
    /**
     * @var AbandonedCollectionFactory
     */
    private AbandonedCollectionFactory $abandonedCollectionFactory;

    /**
     * Cart constructor.
     *
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     */
    public function __construct(AbandonedCollectionFactory $abandonedCollectionFactory)
    {
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public function isClean(string $string): bool
    {
        return ! preg_match("/[^a-zA-Z\d-]/i", $string);
    }

    /**
     * @param string $token
     *
     * @return DataObject|Abandoned|bool
     */
    public function getCart(string $token)
    {
        return $this->abandonedCollectionFactory->create()
            ->loadByToken($token);
    }
}
