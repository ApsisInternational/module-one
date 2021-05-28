<?php

namespace Apsis\One\Model\Events\Historical;

use Apsis\One\Model\Service\Product as ProductServiceProvider;

class EventData
{
    /**
     * @var ProductServiceProvider
     */
    protected $productServiceProvider;

    /**
     * Data constructor.
     *
     * @param ProductServiceProvider $productServiceProvider
     */
    public function __construct(ProductServiceProvider $productServiceProvider)
    {
        $this->productServiceProvider = $productServiceProvider;
    }
}
