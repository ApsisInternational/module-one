<?php

namespace Apsis\One\Service;

use Apsis\One\Controller\Router;
use Apsis\One\Logger\Logger;
use DateTime;
use DateTimeZone;
use DateInterval;
use Exception;
use libphonenumber\PhoneNumberUtil;
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

    // Config keys, Integration
    const PATH_CONFIG_API_KEY = 'apsis_one_connect/api/key';
    const PATH_CONFIG_EVENT_ENABLED = 'apsis_one_configuration/event_sync/enabled';
    const PATH_CONFIG_AC_DURATION = 'apsis_one_configuration/abandoned_cart/duration';
    const PATH_CONFIG_TRACKING_SCRIPT = 'apsis_one_configuration/tracking/script';

    // Config keys, One Api
    const PATH_APSIS_CLIENT_ID = 'apsis_one/api/client_id';
    const PATH_APSIS_CLIENT_SECRET = 'apsis_one/api/client_secret';
    const PATH_APSIS_API_URL = 'apsis_one/api/url';
    const PATH_APSIS_API_TOKEN = 'apsis_one/api/token';
    const PATH_APSIS_API_TOKEN_EXPIRY = 'apsis_one/api/token_expiry';
    const PATH_APSIS_CONFIG_SECTION = 'apsis_one/config/section';
    const PATH_APSIS_CONFIG_KEYSPACE = 'apsis_one/config/profile_key';

    const CONFIG_PATHS_SECURE = [
        self::PATH_CONFIG_API_KEY,
        self::PATH_APSIS_CLIENT_SECRET,
        self::PATH_APSIS_API_TOKEN
    ];

    // APSIS configs
    const EMAIL_DISCRIMINATOR = 'com.apsis1.attributes.email';

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
            $this->log($e->getMessage() . " Store: $storeId");
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
     * @return bool|string
     */
    public function saveStoreConfig(StoreInterface $store, array $configs): bool|string
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
            return $e->getMessage();
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
    public function validateAndFormatMobileNumber(string $countryCode, string $phoneNumber): ?int
    {
        try {
            if (strlen($countryCode) === 2) {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $numberProto = $phoneUtil->parse($phoneNumber, $countryCode);
                if ($phoneUtil->isValidNumber($numberProto)) {
                    return (int) sprintf(
                        '%d%d',
                        (int) $numberProto->getCountryCode(),
                        (int) $numberProto->getNationalNumber()
                    );
                }
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }

        return null;
    }
}
