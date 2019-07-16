<?php

namespace Apsis\One\Model\Config\Source\Abandoned;

class Intervalhour extends Intervalminute
{
    /**
     * @var string
     */
    protected $timeType = 'Hour';

    /**
     * Available times.
     *
     * @var array
     */
    protected $times = [1, 2, 3, 4, 5, 6, 12, 24, 36, 48, 60, 72, 84, 96, 108, 120, 240];
}
