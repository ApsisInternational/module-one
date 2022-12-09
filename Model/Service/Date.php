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
     * @return string|int
     */
    public function formatDateForPlatformCompatibility($date = null, $format = Zend_Date::TIMESTAMP)
    {
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
    public function addSecond($date = null, $format = Zend_Date::TIMESTAMP)
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
    public function getDateTimeFromTimeAndTimeZone($time = 'now', $timezone = 'UTC')
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
    public function getDateTimeFromTime($time = 'now')
    {
        return new dateTime($time);
    }

    /**
     * @param $intervalSpec
     *
     * @return DateInterval
     *
     * @throws Exception
     */
    public function getDateIntervalFromIntervalSpec($intervalSpec)
    {
        return new DateInterval($intervalSpec);
    }

    /**
     * @param string $inputDateTime
     * @param int $day
     *
     * @return string
     */
    public function getFormattedDateTimeWithAddedInterval(string $inputDateTime, int $day = 1)
    {
        try {
            return $this->getDateTimeFromTimeAndTimeZone($inputDateTime)
                ->add($this->getDateIntervalFromIntervalSpec(sprintf('P%sD', $day)))
                ->format(Zend_Date::ISO_8601);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @param string $inputDateTime
     *
     * @return bool
     */
    public function isExpired(string $inputDateTime)
    {
        try {
            $nowDateTime = $this->getDateTimeFromTimeAndTimeZone()->format(Zend_Date::ISO_8601);
            return ($nowDateTime > $inputDateTime);
        } catch (Throwable $e) {
            return false;
        }
    }
}
