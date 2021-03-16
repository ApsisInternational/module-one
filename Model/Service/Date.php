<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\DateInterval;
use Apsis\One\Model\DateIntervalFactory;
use Apsis\One\Model\DateTime;
use Apsis\One\Model\DateTimeFactory;
use Apsis\One\Model\DateTimeZoneFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Zend_Date;

class Date
{
    /**
     * @var TimezoneInterface
     */
    private $localeDate;

    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * @var DateTimeZoneFactory
     */
    private $dateTimeZoneFactory;

    /**
     * @var DateIntervalFactory
     */
    private $dateIntervalFactory;

    /**
     * Date constructor.
     *
     * @param TimezoneInterface $localeDate
     * @param DateTimeFactory $dateTimeFactory
     * @param DateTimeZoneFactory $dateTimeZoneFactory
     * @param DateIntervalFactory $dateIntervalFactory
     */
    public function __construct(
        TimezoneInterface $localeDate,
        DateTimeFactory $dateTimeFactory,
        DateTimeZoneFactory $dateTimeZoneFactory,
        DateIntervalFactory $dateIntervalFactory
    ) {
        $this->dateTimeZoneFactory = $dateTimeZoneFactory;
        $this->dateIntervalFactory = $dateIntervalFactory;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->localeDate = $localeDate;
    }

    /**
     * @param string|null $date
     * @param string $format
     *
     * @return string|int
     */
    public function formatDateForPlatformCompatibility($date = null, $format = Zend_Date::TIMESTAMP)
    {
        return $this->localeDate->date($date)->format($format);
    }

    /**
     * @param string|null $date
     * @param string $format
     *
     * @return string
     */
    public function addSecond($date = null, $format = Zend_Date::TIMESTAMP)
    {
        return (string) $this->localeDate->date($date)
            ->add($this->getDateIntervalFromIntervalSpec('PT1S'))
            ->format($format);
    }

    /**
     * @param string $time
     * @param string $timezone
     *
     * @return DateTime
     */
    public function getDateTimeFromTimeAndTimeZone($time = 'now', $timezone = 'UTC')
    {
        return $this->dateTimeFactory->create(
            [
                'time' => $time,
                'timezone' => $this->dateTimeZoneFactory->create(['timezone' => $timezone])
            ]
        );
    }

    /**
     * @param string $time
     *
     * @return DateTime
     */
    public function getDateTimeFromTime($time = 'now')
    {
        return $this->dateTimeFactory->create(['time' => $time]);
    }

    /**
     * @param $intervalSpec
     *
     * @return DateInterval
     */
    public function getDateIntervalFromIntervalSpec($intervalSpec)
    {
        return $this->dateIntervalFactory->create(
            ['interval_spec' => $intervalSpec]
        );
    }

    /**
     * @param string $inputDateTime
     * @param int $day
     *
     * @return string
     */
    public function getFormattedDateTimeWithAddedInterval(string $inputDateTime, int $day = 1)
    {
        return $this->getDateTimeFromTimeAndTimeZone($inputDateTime)
            ->add($this->getDateIntervalFromIntervalSpec(sprintf('P%sD', $day)))
            ->format(Zend_Date::ISO_8601);
    }

    /**
     * @param string $inputDateTime
     *
     * @return bool
     */
    public function isExpired(string $inputDateTime)
    {
        $nowDateTime = $this->getDateTimeFromTimeAndTimeZone()->format(Zend_Date::ISO_8601);
        return ($nowDateTime > $inputDateTime);
    }
}
