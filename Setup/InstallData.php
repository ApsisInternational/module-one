<?php

namespace Apsis\One\Setup;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\Math\Random;
use Magento\Framework\App\Config\ReinitableConfigInterface;

class InstallData implements InstallDataInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Random
     */
    private $random;

    /**
     * @var ReinitableConfigInterface
     */
    public $configCache;

    /**
     * InstallData constructor.
     *
     * @param Config $config
     * @param Random $random
     * @param ReinitableConfigInterface $configCache
     */
    public function __construct(Config $config, Random $random, ReinitableConfigInterface $configCache)
    {
        $this->configCache = $configCache;
        $this->config = $config;
        $this->random = $random;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $this->populateApsisSubscriberTable($installer);
        $this->savePassCode();
        $this->configCache->reinit();

        $installer->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $installer
     *
     * @return null
     */
    private function populateApsisSubscriberTable($installer)
    {
        $insertArray = [
            'subscriber_id',
            'store_id',
            'customer_id',
            'subscriber_email',
            'subscriber_status'
        ];
        $select = $installer->getConnection()->select()
            ->from(
                [
                    'subscriber' => $installer->getTable(
                        'newsletter_subscriber'
                    )
                ],
                $insertArray
            );

        $sqlQuery = $select->insertFromSelect(
            $installer->getTable(ApsisCoreHelper::APSIS_SUBSCRIBER_TABLE),
            $insertArray,
            false
        );
        $installer->getConnection()->query($sqlQuery);
    }

    /**
     * Generate and save code
     */
    private function savePassCode()
    {
        $this->config->saveConfig(
            ApsisConfigHelper::CONFIG_APSIS_ONE_ABANDONED_CARTS_PASSCODE,
            $this->random->getRandomString(32)
        );
    }
}
