<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\Config\Source\Order\Status;

class OrderStatus implements OptionSourceInterface
{
    /**
     * @var Status
     */
    private $status;

    /**
     * OrderStatus constructor.
     *
     * @param Status $status
     */
    public function __construct(Status $status)
    {
        $this->status = $status;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $statuses = $this->status->toOptionArray();

        if (! empty($statuses) && $statuses[0]['value'] == '') {
            array_shift($statuses);
        }

        $options[] = [
            'label' => __('-- Please Select --'),
            'value' => '0',
        ];

        foreach ($statuses as $status) {
            $options[] = [
                'value' => $status['value'],
                'label' => $status['label'],
            ];
        }

        return $options;
    }
}
