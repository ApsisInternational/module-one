<?php

namespace Apsis\One\Service;

use Apsis\One\Logger\Logger;
use Apsis\One\Model\ResourceModel\Config\ConfigCollection;
use Apsis\One\Model\ConfigModel;
use Apsis\One\Model\ConfigModelFactory;
use Apsis\One\Model\ResourceModel\ConfigResource;
use Apsis\One\Model\ResourceModel\Config\ConfigCollectionFactory;
use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Model\ResourceModel\QueueResource;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\StoreManagerInterface;
use stdClass;
use Throwable;

class ConfigService extends BaseService
{
    /**
     * @var ConfigModelFactory
     */
    private ConfigModelFactory $configModelFactory;

    /**
     * @var ConfigResource
     */
    private ConfigResource $configResource;

    /**
     * @var ConfigCollectionFactory
     */
    private ConfigCollectionFactory $configCollectionFactory;

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param ModuleListInterface $moduleList
     * @param ConfigModelFactory $configModelFactory
     * @param ConfigResource $configResource
     * @param ConfigCollectionFactory $configCollectionFactory
     * @param EventResource $eventResource
     * @param QueueResource $queueResource
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        ModuleListInterface $moduleList,
        ConfigModelFactory $configModelFactory,
        ConfigResource $configResource,
        ConfigCollectionFactory $configCollectionFactory,
        EventResource $eventResource,
        QueueResource $queueResource
    ) {
        parent::__construct($logger, $storeManager, $writer, $moduleList);
        $this->configCollectionFactory = $configCollectionFactory;
        $this->configModelFactory = $configModelFactory;
        $this->configResource = $configResource;
        $this->eventResource = $eventResource;
        $this->queueResource = $queueResource;
    }

    /**
     * @return ConfigCollection
     */
    public function getConfigCollection(): ConfigCollection
    {
        return $this->configCollectionFactory->create();
    }

    /**
     * @return ConfigModel
     */
    private function getConfigModel(): ConfigModel
    {
        return $this->configModelFactory->create();
    }

    /**
     * @param int $storeId
     * @param string $sectionDiscriminator
     * @param array $config
     *
     * @return bool|int
     */
    public function createConfig(int $storeId, string $sectionDiscriminator, array $config): bool|int
    {
        try {
            $configObj = $this->getConfigCollection()->findAConfigForStore($storeId, $this);
            if (! $configObj instanceof ConfigModel) {
                $configObj = $this->getConfigModel();
            }

            $configObj->setStoreId($storeId)
                ->setSectionDiscriminator($sectionDiscriminator)
                ->setIntegrationConfig(json_encode($config))
                ->setErrorMessage('')
                ->setIsActive(1);
            $this->configResource->save($configObj);
            return true;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param int $storeId
     * @param string $sectionDiscriminator
     * @param bool $isSingleSections
     *
     * @return int|array
     */
    public function getConfig(int $storeId, string $sectionDiscriminator, bool $isSingleSections = true): int|array
    {
        try {
            $configs = $this->getConfigCollection()
                ->findConfigBySectionForStore($sectionDiscriminator, $storeId, $this, $isSingleSections);
            if (is_int($configs)) {
                return $configs;
            }

            if ($isSingleSections) {
                return $this->removeClientSecret(json_decode($configs->getIntegrationConfig(), true));
            } else {
                $sConfigs = [];
                /** @var ConfigModel $config */
                foreach ($configs as $config) {
                    $config->afterLoad();
                    $sConfigs[] = $this->removeClientSecret(json_decode($config->getIntegrationConfig(), true));
                }
                return $sConfigs;
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param array $config
     *
     * @return array
     */
    private function removeClientSecret(array $config): array
    {
        if (isset($config['one_api_key']['client_secret'])) {
            $config['one_api_key']['client_secret'] = '';
        }
        return $config;
    }

    /**
     * @param int $storeId
     * @param string $sectionDiscriminator
     *
     * @return bool|int
     */
    public function deleteConfig(int $storeId, string $sectionDiscriminator): bool|int
    {
        try {
            $config = $this->getConfigCollection()
                ->findConfigBySectionForStore($sectionDiscriminator, $storeId, $this);
            if (is_int($config)) {
                return $config;
            }

            $this->configResource->delete($config);
            $this->eventResource->setHistoricalStatusOnAllEvents($storeId, $this);
            $this->queueResource->deleteAllPendingItemsForStore($storeId, $this);
            $this->saveStoreConfig(
                $this->getStore($storeId),
                [
                    BaseService::PATH_CONFIG_AC_DURATION => 15,
                    BaseService::PATH_CONFIG_TRACKING_SCRIPT => '',
                    BaseService::PATH_CONFIG_EVENT_PREVIOUS_HISTORICAL => ''
                ]
            );

            return true;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param int $storeId
     *
     * @return ConfigModel|null
     */
    public function getActiveConfigForStore(int $storeId): ?ConfigModel
    {
        try {
            $collection = $this->getConfigCollection()->getActiveConfigForStore($storeId);
            return $collection->getSize() ? $collection->getFirstItem()->afterLoad() : null;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param ConfigModel $configModel
     * @param stdClass $response
     *
     * @return string
     */
    public function saveApiTokenAndExpiry(ConfigModel $configModel, stdClass $response): string
    {
        try {
            $time = $this->getDateTimeFromTimeAndTimeZone()
                ->add($this->getDateIntervalFromIntervalSpec(sprintf('PT%sS', $response->expires_in)))
                ->sub($this->getDateIntervalFromIntervalSpec('PT60M'))
                ->format('Y-m-d H:i:s');

            $configModel->setApiToken($response->access_token)
                ->setApiTokenExpiry($time);
            $this->configResource->save($configModel);
            return (string) $response->access_token;
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param ConfigModel $configModel
     * @param stdClass $response
     *
     * @return void
     */
    public function markConfigInactive(ConfigModel $configModel, stdClass $response): void
    {
        try {
            $configModel->setIsActive(0)
                ->setApiToken('')
                ->setApiTokenExpiry('')
                ->setErrorMessage(sprintf('Error: %s - Code: %s', $response->title, $response->status));
            $this->configResource->save($configModel);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }
}
