<?php

namespace Apsis\One\Model;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Apsis\One\Model\ResourceModel\ProfileBatch;
use Apsis\One\Model\ResourceModel\Profile;
use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\ResourceModel\Abandoned;
use Magento\Config\Model\ResourceModel\Config as configResource;
use Exception;

class Developer
{
    /**
     * @var ReinitableConfigInterface
     */
    private $config;

    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * @var ProfileBatch
     */
    private $profileBatch;

    /**
     * @var Profile
     */
    private $profile;

    /**
     * @var Event
     */
    private $event;

    /**
     * @var Abandoned
     */
    private $abandoned;

    /**
     * @var configResource
     */
    private $configResource;

    /**
     * Developer constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param ReinitableConfigInterface $reinitableConfig
     * @param ProfileBatch $profileBatch
     * @param Profile $profile
     * @param Event $event
     * @param configResource $configResource
     * @param Abandoned $abandoned
     */
    public function __construct(
        ApsisLogHelper $apsisLogHelper,
        ReinitableConfigInterface $reinitableConfig,
        ProfileBatch $profileBatch,
        Profile $profile,
        Event $event,
        configResource $configResource,
        Abandoned $abandoned
    ) {
        $this->abandoned = $abandoned;
        $this->config = $reinitableConfig;
        $this->apsisLogHelper = $apsisLogHelper;
        $this->profileBatch = $profileBatch;
        $this->profile = $profile;
        $this->event = $event;
        $this->configResource = $configResource;
    }

    /**
     * @return bool
     */
    public function resetModule()
    {
        return (
            $this->profileBatch->truncateTable($this->apsisLogHelper) &&
            $this->event->truncateTable($this->apsisLogHelper) &&
            $this->abandoned->truncateTable($this->apsisLogHelper) &&
            $this->profile->truncateTable($this->apsisLogHelper) &&
            $this->profile->populateProfilesTable($this->apsisLogHelper) &&
            $this->deleteAllModuleConfig()
        );
    }

    /**
     * @return bool
     */
    private function deleteAllModuleConfig()
    {
        try {
            $connection = $this->configResource->getConnection();
            $connection->delete(
                $this->configResource->getMainTable(),
                "path like '%apsis_one%'"
            );
            $this->config->reinit();
            return true;
        } catch (Exception $e) {
            $this->apsisLogHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }
}
