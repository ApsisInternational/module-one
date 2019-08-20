<?php

namespace Apsis\One\Logger\Handler;

use Monolog\Logger;
use Magento\Framework\Logger\Handler\Base;

class ApsisLogHandler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/apsis_one.log';
}
