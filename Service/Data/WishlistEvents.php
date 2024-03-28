<?php

namespace Apsis\One\Service\Data;

use Apsis\One\Model\EventModel;
use Apsis\One\Service\Data\Wishlist\WishlistData;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Wishlist\Model\Item as MagentoWishlistItem;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistItemCollectionFactory;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory as WishlistCollectionFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class WishlistEvents extends AbstractEvents
{
    /**
     * @var WishlistCollectionFactory
     */
    private WishlistCollectionFactory $wishlistCollectionFactory;

    /**
     * @var WishlistItemCollectionFactory
     */
    private WishlistItemCollectionFactory $wishlistItemCollectionFactory;

    /**
     * @param DateTime $dateTime
     * @param EventResource $eventResource
     * @param WishlistData $entityData
     * @param WishlistItemCollectionFactory $wishlistItemCollectionFactory
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     */
    public function __construct(
        DateTime $dateTime,
        EventResource $eventResource,
        WishlistData $entityData,
        WishlistItemCollectionFactory $wishlistItemCollectionFactory,
        WishlistCollectionFactory $wishlistCollectionFactory
    ) {
        $this->wishlistItemCollectionFactory = $wishlistItemCollectionFactory;
        $this->wishlistCollectionFactory = $wishlistCollectionFactory;
        parent::__construct($dateTime, $eventResource, $entityData);
    }

    /**
     * @inheirtDoc
     */
    public function getCollection(int $storeId, array $ids): AbstractCollection
    {
        $collection =  $this->wishlistItemCollectionFactory
            ->create()
            ->addStoreFilter([$storeId])
            ->addFieldToFilter('wishlist_id', ['in' => $ids])
            ->addFieldToFilter('main_table.added_at', $this->fetchDuration)
            ->setVisibilityFilter()
            ->setSalableFilter();
        $collection->getSelect()->group('wishlist_item_id');
        return $collection;
    }

    /**
     * @inheirtDoc
     */
    public function getEventsArr(BaseService $service, array $collection, array $profiles, int $storeId): array
    {
        $wishlistItemCollection = $this->getArrayFromEntityCollection(array_keys($collection), $storeId);
        $events = [];
        /** @var  MagentoWishlistItem $item */
        foreach ($wishlistItemCollection as $item) {
            $wishList = $collection[$item->getWishlistId()];
            $profile = $profiles[$wishList->getCustomerId()];

            $data = ['main' => $this->entityData->getDataArr($item, $service), 'sub' => ''];
            $events[] = $this->getDataForInsertion($profile, EventModel::WISHED, $item->getAddedAt(), $data);
        }
        return $events;
    }

    /**
     * @inheirtDoc
     */
    protected function findEvents(StoreInterface $store, BaseService $baseService, array $profileColArray): array
    {
        $wishListCollection = $this->getWishlistCollection(array_keys($profileColArray));
        return $this->getEventsArr($baseService, $wishListCollection, $profileColArray, $store->getId());
    }

    /**
     * @param array $customerIds
     *
     * @return array
     */
    private function getWishlistCollection(array $customerIds): array
    {
        $collectionArray = [];
        foreach (array_chunk($customerIds, self::QUERY_LIMIT) as $customerIdsChunk) {
            $collection = $this->wishlistCollectionFactory
                ->create()
                ->filterByCustomerIds($customerIdsChunk);
            foreach ($collection as $item) {
                $collectionArray[$item->getId()] =  $item;
            }
        }
        return $collectionArray;
    }
}
