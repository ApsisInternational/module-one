<?php

namespace Apsis\One\Model\Config\Source\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\Config\Source\Order\Status;

class OrderStatus implements OptionSourceInterface
{
    /**
     * @var Status
     */
    private Status $status;

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
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [['label' => __('-- Please Select --'), 'value' => '0']];

        $statuses = $this->status->toOptionArray();

        if (! empty($statuses) && empty($statuses[0]['value'])) {
            array_shift($statuses);
        }

        foreach ($statuses as $status) {
            $options[] = ['value' => $status['value'], 'label' => __($status['label'])];
        }

        return $options;
    }
}
