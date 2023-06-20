<?php

namespace Apsis\One\Model\ResourceModel\Profile;

use Apsis\One\Model\ResourceModel\AbstractCollection;
use Apsis\One\Model\ResourceModel\ProfileResource;
use Apsis\One\Model\ProfileModel;

class ProfileCollection extends AbstractCollection
{
    const MODEL = ProfileModel::class;
    const RESOURCE_MODEL = ProfileResource::class;

    /**
     * @param int $storeId
     *
     * @return $this
     */
    public function getProfileCollectionForStore(int $storeId): ProfileCollection
    {
        return $this->getCollection('store_id', $storeId);
    }
}
