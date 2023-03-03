<?php

namespace Apsis\One\Model\Service;

use DateTime;
use DateTimeZone;
use DateInterval;
use Exception;
use Throwable;
use Zend_Date;

class Date
{
    /**
     * @param string|null $date
     * @param string $format
     *
     * @return string
     */
    public function formatDateForPlatformCompatibility(
        string $date = null,
        string $format = Zend_Date::TIMESTAMP
    ): string {
        if (empty($date)) {
            $date = 'now';
        }

        try {
            return $this->getDateTimeFromTime($date)->format($format);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @param string|null $date
     * @param string $format
     *
     * @return string
     */
    public function addSecond(string $date = null, string $format = Zend_Date::TIMESTAMP): string
    {
        if (empty($date)) {
            $date = 'now';
        }

        try {
            return (string) $this->getDateTimeFromTime($date)
                ->add($this->getDateIntervalFromIntervalSpec('PT1S'))
                ->format($format);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @param string $time
     * @param string $timezone
     *
     * @return DateTime
     *
     * @throws Exception
     */
    public function getDateTimeFromTimeAndTimeZone(string $time = 'now', string $timezone = 'UTC'): DateTime
    {
        return new dateTime($time, new dateTimeZone($timezone));
    }

    /**
     * @param string $time
     *
     * @return DateTime
     *
     * @throws Exception
     */
    public function getDateTimeFromTime(string $time = 'now'): DateTime
    {
        return new dateTime($time);
    }

    /**
     * @param string $intervalSpec
     *
     * @return DateInterval
     *
     * @throws Exception
     */
    public function getDateIntervalFromIntervalSpec(string $intervalSpec): DateInterval
    {
        return new DateInterval($intervalSpec);
    }
}
