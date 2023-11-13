<?php

namespace Apsis\One\Service;

use Apsis\One\Controller\Router;
use Apsis\One\Logger\Logger;
use DateTime;
use DateTimeZone;
use DateInterval;
use Exception;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class BaseService
{
    // DB Tables
    const APSIS_PROFILE_TABLE = 'apsis_profile';
    const APSIS_EVENT_TABLE = 'apsis_event';
    const APSIS_ABANDONED_TABLE = 'apsis_abandoned';
    const APSIS_WEBHOOK_TABLE = 'apsis_webhook';
    const APSIS_QUEUE_TABLE = 'apsis_queue';
    const APSIS_CONFIG_TABLE = 'apsis_config';

    // Config keys, Integration
    const PATH_CONFIG_API_KEY = 'apsis_one_connect/api/key';
    const PATH_CONFIG_EVENT_ENABLED = 'apsis_one_configuration/event_sync/enabled';
    const PATH_CONFIG_EVENT_PREVIOUS_HISTORICAL = 'apsis_one_configuration/event_sync/previous_historical';
    const PATH_CONFIG_AC_DURATION = 'apsis_one_configuration/abandoned_cart/duration';
    const PATH_CONFIG_TRACKING_SCRIPT = 'apsis_one_configuration/tracking/script';

    const CONFIG_PATHS_SECURE = [
        self::PATH_CONFIG_API_KEY
    ];

    // APSIS configs
    const EMAIL_DISCRIMINATOR = 'com.apsis1.attributes.email';

    /**
     * @link https://countrycode.org/
     */
    const CALLING_CODES = [
        'AF' => '93',
        'AL' => '355',
        'DZ' => '213',
        'AS' => '1-684',
        'AD' => '376',
        'AO' => '244',
        'AI' => '1-264',
        'AQ' => '672',
        'AG' => '1-268',
        'AR' => '54',
        'AM' => '374',
        'AW' => '297',
        'AU' => '61',
        'AT' => '43',
        'AZ' => '994',
        'BS' => '1-242',
        'BH' => '973',
        'BD' => '880',
        'BB' => '1-246',
        'BY' => '375',
        'BE' => '32',
        'BZ' => '501',
        'BJ' => '229',
        'BM' => '1-441',
        'BT' => '975',
        'BO' => '591',
        'BA' => '387',
        'BW' => '267',
        'BR' => '55',
        'IO' => '246',
        'VG' => '1-284',
        'BN' => '673',
        'BG' => '359',
        'BF' => '226',
        'BI' => '257',
        'KH' => '855',
        'CM' => '237',
        'CA' => '1',
        'CV' => '238',
        'KY' => '1-345',
        'CF' => '236',
        'TD' => '235',
        'CL' => '56',
        'CN' => '86',
        'CX' => '61',
        'CC' => '61',
        'CO' => '57',
        'KM' => '269',
        'CK' => '682',
        'CR' => '506',
        'HR' => '385',
        'CU' => '53',
        'CW' => '599',
        'CY' => '357',
        'CZ' => '420',
        'CD' => '243',
        'DK' => '45',
        'DJ' => '253',
        'DM' => '1-767',
        'DO' => '1-809',
        'TL' => '670',
        'EC' => '593',
        'EG' => '20',
        'SV' => '503',
        'GQ' => '240',
        'ER' => '291',
        'EE' => '372',
        'ET' => '251',
        'FK' => '500',
        'FO' => '298',
        'FJ' => '679',
        'FI' => '358',
        'FR' => '33',
        'PF' => '689',
        'GA' => '241',
        'GM' => '220',
        'GE' => '995',
        'DE' => '49',
        'GH' => '233',
        'GI' => '350',
        'GR' => '30',
        'GL' => '299',
        'GD' => '1-473',
        'GU' => '1-671',
        'GT' => '502',
        'GG' => '44-1481',
        'GN' => '224',
        'GW' => '245',
        'GY' => '592',
        'HT' => '509',
        'HN' => '504',
        'HK' => '852',
        'HU' => '36',
        'IS' => '354',
        'IN' => '91',
        'ID' => '62',
        'IR' => '98',
        'IQ' => '964',
        'IE' => '353',
        'IM' => '44-1624',
        'IL' => '972',
        'IT' => '39',
        'CI' => '225',
        'JM' => '1-876',
        'JP' => '81',
        'JE' => '44-1534',
        'JO' => '962',
        'KZ' => '7',
        'KE' => '254',
        'KI' => '686',
        'XK' => '383',
        'KW' => '965',
        'KG' => '996',
        'LA' => '856',
        'LV' => '371',
        'LB' => '961',
        'LS' => '266',
        'LR' => '231',
        'LY' => '218',
        'LI' => '423',
        'LT' => '370',
        'LU' => '352',
        'MO' => '853',
        'MK' => '389',
        'MG' => '261',
        'MW' => '265',
        'MY' => '60',
        'MV' => '960',
        'ML' => '223',
        'MT' => '356',
        'MH' => '692',
        'MR' => '222',
        'MU' => '230',
        'YT' => '262',
        'MX' => '52',
        'FM' => '691',
        'MD' => '373',
        'MC' => '377',
        'MN' => '976',
        'ME' => '382',
        'MS' => '1-664',
        'MA' => '212',
        'MZ' => '258',
        'MM' => '95',
        'NA' => '264',
        'NR' => '674',
        'NP' => '977',
        'NL' => '31',
        'AN' => '599',
        'NC' => '687',
        'NZ' => '64',
        'NI' => '505',
        'NE' => '227',
        'NG' => '234',
        'NU' => '683',
        'KP' => '850',
        'MP' => '1-670',
        'NO' => '47',
        'OM' => '968',
        'PK' => '92',
        'PW' => '680',
        'PS' => '970',
        'PA' => '507',
        'PG' => '675',
        'PY' => '595',
        'PE' => '51',
        'PH' => '63',
        'PN' => '64',
        'PL' => '48',
        'PT' => '351',
        'PR' => '1-787',
        'QA' => '974',
        'CG' => '242',
        'RE' => '262',
        'RO' => '40',
        'RU' => '7',
        'RW' => '250',
        'BL' => '590',
        'SH' => '290',
        'KN' => '1-869',
        'LC' => '1-758',
        'MF' => '590',
        'PM' => '508',
        'VC' => '1-784',
        'WS' => '685',
        'SM' => '378',
        'ST' => '239',
        'SA' => '966',
        'SN' => '221',
        'RS' => '381',
        'SC' => '248',
        'SL' => '232',
        'SG' => '65',
        'SX' => '1-721',
        'SK' => '421',
        'SI' => '386',
        'SB' => '677',
        'SO' => '252',
        'ZA' => '27',
        'KR' => '82',
        'SS' => '211',
        'ES' => '34',
        'LK' => '94',
        'SD' => '249',
        'SR' => '597',
        'SJ' => '47',
        'SZ' => '268',
        'SE' => '46',
        'CH' => '41',
        'SY' => '963',
        'TW' => '886',
        'TJ' => '992',
        'TZ' => '255',
        'TH' => '66',
        'TG' => '228',
        'TK' => '690',
        'TO' => '676',
        'TT' => '1-868',
        'TN' => '216',
        'TR' => '90',
        'TM' => '993',
        'TC' => '1-649',
        'TV' => '688',
        'VI' => '1-340',
        'UG' => '256',
        'UA' => '380',
        'AE' => '971',
        'GB' => '44',
        'US' => '1',
        'UY' => '598',
        'UZ' => '998',
        'VU' => '678',
        'VA' => '379',
        'VE' => '58',
        'VN' => '84',
        'WF' => '681',
        'EH' => '212',
        'YE' => '967',
        'ZM' => '260',
        'ZW' => '263'
    ];

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var WriterInterface
     */
    protected WriterInterface $writer;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;

    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        ModuleListInterface $moduleList
    ) {
        $this->moduleList = $moduleList;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->writer = $writer;
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function log(string $message): void
    {
        $this->logger->info($this->addModuleVersionToMessage($message));
    }

    /**
     * @param string $message
     * @param array $response
     *
     * @return void
     */
    public function debug(string $message, array $response = []): void
    {
        $info = [
            'Message' => $message,
            'Information' => $response
        ];
        $this->logger->debug($this->getStringForLog($info));
    }

    /**
     * @param string $classMethodName
     * @param Throwable $e
     *
     * @return void
     */
    public function logError(string $classMethodName, Throwable $e): void
    {
        $info = [
            'Method' => $classMethodName,
            'Exception|Error' => $e->getMessage(),
            'Trace' => str_replace(PHP_EOL, PHP_EOL . '        ', PHP_EOL . $e->getTraceAsString())
        ];
        $this->logger->error($this->getStringForLog($info));
    }

    /**
     * @param array $info
     *
     * @return string
     */
    private function getStringForLog(array $info): string
    {
        return stripcslashes($this->addModuleVersionToMessage(json_encode($info, JSON_PRETTY_PRINT)));
    }

    /**
     * @param string $message
     *
     * @return string
     */
    private function addModuleVersionToMessage(string $message): string
    {
        return '(Module v' . $this->getCurrentVersion() . ') ' . $message . PHP_EOL . '*** -------------------- ***';
    }

    /**
     * @param string $method
     * @param int $startTime
     * @param int $startMemory
     *
     * @return void
     */
    public function logPerformanceData(string $method, float $startTime, int $startMemory): void
    {
        if (getenv('APSIS_DEVELOPER')) {
            $this->debug(
                $method,
                [
                    sprintf('Execution time: %s seconds', microtime(true) - $startTime),
                    sprintf('Peak memory usage: %s bytes', memory_get_peak_usage() - $startMemory)
                ]
            );
        }
    }

    /**
     * @return string
     */
    public function getCurrentVersion(): string
    {
        try {
            $moduleInfo = $this->moduleList->getOne('Apsis_One');
            if (is_array($moduleInfo) && ! empty($moduleInfo['setup_version'])) {
                return (string) $moduleInfo['setup_version'];
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }

        return 'unknown';
    }

    /**
     * @param string|null $date
     * @param string $format
     *
     * @return string
     */
    public function formatDateForPlatformCompatibility(string $date = null, string $format = 'U'): string
    {
        if (empty($date)) {
            $date = 'now';
        }

        try {
            return $this->getDateTimeFromTime($date)->format($format);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param string|null $date
     * @param string $format
     *
     * @return string
     */
    public function addSecond(string $date = null, string $format = 'U'): string
    {
        if (empty($date)) {
            $date = 'now';
        }

        try {
            return (string) $this->getDateTimeFromTime($date)
                ->add($this->getDateIntervalFromIntervalSpec('PT1S'))
                ->format($format);
        } catch (Throwable) {
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
     * @return string
     */
    public function formatCurrentDateToInternalFormat(): string
    {
        try {
            return $this->getDateTimeFromTime()
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return '';
        }
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

    /**
     * @param int|string|null $storeId
     *
     * @return bool|StoreInterface
     */
    public function getStore(int|string $storeId = null): StoreInterface|bool
    {
        try {
            return $this->storeManager->getStore($storeId);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param bool $withDefault
     *
     * @return StoreInterface[]
     */
    public function getStores(bool $withDefault = false): array
    {
        try {
            return $this->storeManager->getStores($withDefault);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param null|int $storeId
     *
     * @return string
     */
    public function getStoreName(int $storeId = null): string
    {
        $store = $this->getStore($storeId);
        return ($store) ? $store->getName() : '';
    }

    /**
     * @param null|int $storeId
     *
     * @return string
     */
    public function getStoreCurrency(int $storeId = null): string
    {
        $store = $this->getStore($storeId);
        return ($store) ? $this->getStoreConfig($store, 'currency/options/default') : '';
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    public function isStoreFrontSecure(int $storeId): bool
    {
        try {
            $store = $this->getStore($storeId);
            return $store instanceof StoreInterface && $store->isFrontUrlSecure();
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param int $storeId
     *
     * @return string
     */
    public function getStoreBaseUrl(int $storeId): string
    {
        try {
            $store = $this->getStore($storeId);
            return $store ? $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $this->isStoreFrontSecure($storeId)) : '';
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param null|int $storeId
     *
     * @return string
     */
    public function getStoreWebsiteName(int $storeId = null): string
    {
        try {
            $store = $this->getStore($storeId);
            return ($store) ? $this->storeManager->getWebsite($store->getWebsiteId())->getName() : '';
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $path
     *
     * @return string|null
     */
    public function getStoreConfig(StoreInterface $store, string $path): ?string
    {
        try {
            return $store->getConfig($path);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param StoreInterface $store
     * @param array $configs
     *
     * @return bool
     */
    public function saveStoreConfig(StoreInterface $store, array $configs): bool
    {
        try {
            foreach ($configs as $path => $value) {
                $this->writer->save($path, $value, ScopeInterface::SCOPE_STORES, $store->getId());

                $info = [
                    'Store Id' => $store->getId(),
                    'Config Path' => $path,
                    'Old Value' => $this->getStoreConfig($store, $path),
                    'New Value' => $value
                ];
                if (in_array($path, self::CONFIG_PATHS_SECURE)) {
                    $info['Old Value'] = $info['New Value'] = 'An encrypted value.';
                }
                $this->debug(__METHOD__, $info);
            }

            // Reset config after save
            $store->resetConfig();
            return true;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @param string $path
     * @param string $value
     *
     * @return void
     */
    public function saveDefaultConfig(string $path, string $value): void
    {
        try {
            $info = [
                'Store Id' => 0,
                'Config Path' => $path,
                'Old Value' => $this->getStoreConfig($this->getStore(0), $path),
                'New Value' => $value
            ];
            if (in_array($path, self::CONFIG_PATHS_SECURE)) {
                $info['Old Value'] = $info['New Value'] = 'An encrypted value.';
            }
            $this->debug(__METHOD__, $info);

            $this->writer->save($path, $value);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param RequestInterface $request
     *
     * @return string
     */
    public function generateSystemAccessUrl(RequestInterface $request): string
    {
        try {
            $store = $this->getStore($request->getParam('store'));
            return $store->getBaseUrl() . $store->getCode() . '/' . Router::API_ROUTE;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public function isClean(string $string): bool
    {
        return ! preg_match('/[^a-zA-Z\d-]/i', $string);
    }

    /**
     * VALID RFC 4211 COMPLIANT Universally Unique Identifier (UUID) version 4
     * https://www.php.net/manual/en/function.uniqid.php#94959
     *
     * @return string
     */
    public static function generateUniversallyUniqueIdentifier(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * @param StoreInterface $store
     *
     * @return string
     */
    public function getDomainFromBaseUrl(StoreInterface $store): string
    {
        $domain = '';
        try {
            $host = (string) parse_url($store->getBaseUrl(), PHP_URL_HOST);
            if (! empty($host) && ! empty($hostArr = explode('.', $host))) {
                if (count($hostArr) > 3) {
                    $domain = sprintf('.%s', $host);
                } else {
                    $TLD = array_pop($hostArr);
                    $SLD = array_pop($hostArr);
                    $domain = sprintf('.%s.%s', $SLD, $TLD);
                }
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
        return $domain;
    }

    /**
     * @param $data
     *
     * @return string
     */
    public static function escapeQuote($data): string
    {
        return htmlspecialchars($data, ENT_QUOTES|ENT_SUBSTITUTE, null, false);
    }

    /**
     * @param string $countryCode
     * @param string $phoneNumber
     *
     * @return int|null
     */
    public function formatPhoneNumber(string $countryCode, string $phoneNumber): ?int
    {
        try {
            if (strlen($countryCode) === 2 && strlen($phoneNumber) && isset(self::CALLING_CODES[$countryCode])) {
                return (int) preg_replace('/[^0-9]/', '', self::CALLING_CODES[$countryCode] . (int) $phoneNumber);
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
        return null;
    }
}
