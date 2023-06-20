<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ResourceModel\Store\Collection;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;

class StoreSourceModel implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return Collection
     */
    private function getStoreCollection(): Collection
    {
        return $this->collectionFactory->create();
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $stores = $this->getStoreCollection()->toOptionArray();
        $stores[] = ['value' => null, 'label' => 'N/A'];
        return $stores;
    }
}
