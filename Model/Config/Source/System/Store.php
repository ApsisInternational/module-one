<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;

class Store implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * Store constructor.
     *
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $stores = $this->collectionFactory->create()->toOptionArray();
        $stores[] = ['value' => null, 'label' => 'N/A'];
        return $stores;
    }
}
