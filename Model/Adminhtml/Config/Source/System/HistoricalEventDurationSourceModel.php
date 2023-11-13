<?php

namespace Apsis\One\Model\Adminhtml\Config\Source\System;

class HistoricalEventDurationSourceModel extends AbstractOptionsSource
{
    const VALUE_TO_TEXT = [
        0 => '',
        1 => '1 MONTH',
        3 => '3 MONTH',
        6 => '6 MONTH',
        12 => '12 MONTH',
        24 => '24 MONTH',
        36 => '36 MONTH',
        48 => '48 MONTH',
    ];

    /**
     * @inheritdoc
     */
    protected function getOptionTextMap(): array
    {
        return self::VALUE_TO_TEXT;
    }
}
