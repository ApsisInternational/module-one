<?php

namespace Apsis\One\Model\ResourceModel\Abandoned;

use Apsis\One\Model\ResourceModel\AbstractCollection;
use Apsis\One\Model\ResourceModel\AbandonedResource;
use Apsis\One\Model\AbandonedModel;

class AbandonedCollection extends AbstractCollection
{
    const MODEL = AbandonedModel::class;
    const RESOURCE_MODEL = AbandonedResource::class;
}
